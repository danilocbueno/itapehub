<?php

namespace App\Programas;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Procura episódios novos nas páginas do canal (aba "Ao vivo" e, se houver,
 * "Vídeos"): vídeos com o título no padrão do programa, já encerrados (com
 * duração mínima) e transmitidos nos últimos dias (nunca antes de "since").
 * Cada episódio novo é gravado sem notícia, à espera do Gemini.
 */
final class EpisodeFinder
{
    public function __construct(private EpisodeRepository $episodes) {}

    /**
     * @return array{listed: int, new: int, errors: list<string>}
     */
    public function find(Program $program, CarbonImmutable $now): array
    {
        $videos = [];
        $errors = [];

        foreach ($program->pages as $page) {
            try {
                foreach ($this->videos($page) as $video) {
                    $videos[$video['id']] ??= $video;
                }
            } catch (RuntimeException|ConnectionException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($videos === [] && $errors !== []) {
            throw new RuntimeException(implode(' | ', $errors));
        }

        $from = max(
            $now->startOfDay()->subDays((int) config('itape.programas.days') - 1),
            CarbonImmutable::parse((string) config('itape.programas.since'), 'America/Sao_Paulo')->startOfDay(),
        );
        $listed = 0;
        $new = 0;

        foreach ($videos as $video) {
            $parsed = $program->parseTitle($video['title']);

            if ($parsed === null) {
                continue;
            }

            $listed++;

            if ($video['seconds'] === null || $video['seconds'] < $program->minMinutes * 60
                || $parsed['date'] < $from || $parsed['date'] > $now
                || $this->episodes->find($program->key, $video['id']) !== null) {
                continue;
            }

            $this->episodes->save([
                'program' => $program->key,
                'video' => $video['id'],
                'title' => $video['title'],
                'topic' => $parsed['topic'],
                'date' => $parsed['date']->format('Y-m-d'),
                'seconds' => $video['seconds'],
                'found_at' => $now->toIso8601String(),
            ]);
            $new++;
        }

        return ['listed' => $listed, 'new' => $new, 'errors' => $errors];
    }

    /**
     * @return list<array{id: string, title: string, seconds: int|null}>
     */
    private function videos(string $page): array
    {
        $response = Http::timeout((int) config('itape.http.timeout'))
            ->withHeaders([
                'User-Agent' => (string) config('itape.http.user_agent'),
                'Accept-Language' => 'pt-BR,pt;q=0.9',
            ])
            ->get($page);

        if (! $response->successful()) {
            throw new RuntimeException("YouTube respondeu HTTP {$response->status()} ({$page})");
        }

        return ChannelParser::videos($response->body());
    }
}
