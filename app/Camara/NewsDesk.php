<?php

namespace App\Camara;

use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Escreve a notícia de cada sessão encerrada: pelo Gemini quando configurado;
 * se ele faltar, falhar ou devolver um texto que não passa na conferência,
 * pelo modelo fixo. Uma notícia do modelo fixo volta a tentar o Gemini nas
 * execuções seguintes (até max_attempts) e é trocada quando ele responder.
 * Grava em data/camara/noticias/<id>.json com o método usado.
 */
final class NewsDesk
{
    public function __construct(
        private string $directory,
        private Names $names,
        private ?GeminiWriter $gemini,
        private TemplateWriter $template = new TemplateWriter,
    ) {}

    /**
     * @param  list<Session>  $sessions
     * @return list<array{session: int, by: string, error: string|null}>
     */
    public function write(array $sessions, CarbonImmutable $now): array
    {
        $written = [];

        foreach ($sessions as $session) {
            $existing = $this->find($session->id);

            if (! $session->complete() || ! $this->needsWriting($existing)) {
                continue;
            }

            $short = $this->names->apply($session);
            $attempts = (int) ($existing['gemini_attempts'] ?? 0);
            $error = null;
            $article = null;
            $by = 'modelo';

            if ($this->gemini !== null) {
                $attempts++;

                try {
                    $answer = $this->gemini->write($short);
                    $article = Article::fromArray($answer['data'], $this->names->known());
                    $by = 'gemini:'.$answer['model'];
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }

            // O modelo fixo não muda entre tentativas: sem Gemini novo, não regrava.
            if ($article === null && $existing !== null) {
                JsonFile::write($this->path($session->id), [...$existing, 'gemini_attempts' => $attempts, 'gemini_error' => $error]);
                $written[] = ['session' => $session->id, 'by' => 'modelo (mantido)', 'error' => $error];

                continue;
            }

            JsonFile::write($this->path($session->id), array_filter([
                'session' => $session->id,
                'title' => $session->title,
                'date' => $session->date,
                'url' => $session->url,
                'written_by' => $by,
                'written_at' => $now->toIso8601String(),
                'gemini_attempts' => $article === null ? $attempts : null,
                'gemini_error' => $error,
                'article' => ($article ?? $this->template->write($short))->toArray(),
            ], fn ($value) => $value !== null));

            $written[] = ['session' => $session->id, 'by' => $by, 'error' => $error];
        }

        return $written;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return JsonFile::read($this->path($id));
    }

    /**
     * Sem notícia, ou do modelo fixo com o Gemini disponível e tentativas sobrando.
     *
     * @param  array<string, mixed>|null  $existing
     */
    private function needsWriting(?array $existing): bool
    {
        if ($existing === null) {
            return true;
        }

        return $this->gemini !== null
            && ! str_starts_with((string) ($existing['written_by'] ?? ''), 'gemini')
            && (int) ($existing['gemini_attempts'] ?? 0) < (int) config('itape.camara.gemini.max_attempts');
    }

    private function path(int $id): string
    {
        return $this->directory.'/'.$id.'.json';
    }
}
