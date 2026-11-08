<?php

namespace App\Programas;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Escreve as notícias dos episódios ainda sem texto, de todos os programas, do
 * mais novo para o mais antigo, até per_run por execução (cada vídeo longo
 * gasta perto do limite de tokens por minuto da cota gratuita).
 *
 * Se o Gemini falhar ou o texto não passar na conferência, nada é publicado: o
 * erro fica gravado e o episódio volta a ser tentado nas execuções seguintes
 * (até max_attempts). Recusa por cota (429) não conta como tentativa e encerra
 * a execução.
 */
final class ProgramDesk
{
    public function __construct(
        private EpisodeRepository $episodes,
        private VideoWriter $writer,
    ) {}

    /**
     * @param  array<string, Program>  $programs
     * @return list<array{program: string, video: string, by: string|null, error: string|null}>
     */
    public function write(array $programs, CarbonImmutable $now): array
    {
        $done = [];
        $limit = (int) config('itape.programas.per_run');

        foreach ($this->pending($programs) as [$program, $episode]) {
            if (count($done) >= $limit) {
                break;
            }

            // Gravada antes de chamar o Gemini: se o passo for interrompido no meio, a tentativa conta.
            $attempts = (int) ($episode['attempts'] ?? 0);
            $episode['attempts'] = $attempts + 1;
            $this->episodes->save($episode);
            $limited = false;

            try {
                $answer = $this->writer->write($program, $episode);
                $date = CarbonImmutable::parse((string) $episode['date'], 'America/Sao_Paulo');
                $episode['article'] = VideoStory::check($answer['data'], (int) $episode['seconds'], $date);
                $episode['written_by'] = 'gemini:'.$answer['model'];
                $episode['written_at'] = $now->toIso8601String();
                unset($episode['error']);
            } catch (RateLimited $e) {
                $episode['attempts'] = $attempts;
                $episode['error'] = mb_strimwidth($e->getMessage(), 0, 400, '…');
                $limited = true;
            } catch (Throwable $e) {
                $episode['error'] = mb_strimwidth($e->getMessage(), 0, 400, '…');
            }

            $this->episodes->save($episode);
            $done[] = [
                'program' => $program->name,
                'video' => (string) $episode['video'],
                'by' => isset($episode['article']) ? (string) $episode['written_by'] : null,
                'error' => isset($episode['article']) ? null : (string) $episode['error'],
            ];

            if ($limited) {
                break;
            }
        }

        return $done;
    }

    /**
     * Episódios sem notícia e com tentativas sobrando, do mais novo para o mais antigo.
     *
     * @param  array<string, Program>  $programs
     * @return list<array{0: Program, 1: array<string, mixed>}>
     */
    private function pending(array $programs): array
    {
        $max = (int) config('itape.programas.max_attempts');
        $pending = [];

        foreach ($programs as $program) {
            foreach ($this->episodes->all($program->key) as $episode) {
                if (! isset($episode['article']) && (int) ($episode['attempts'] ?? 0) < $max) {
                    $pending[] = [$program, $episode];
                }
            }
        }

        // Estável: no mesmo dia, vale a ordem de config/programas.php.
        usort($pending, fn (array $a, array $b) => strcmp((string) $b[1]['date'], (string) $a[1]['date']));

        return $pending;
    }
}
