<?php

namespace App\Camara;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Escreve a notícia da sessão com o Gemini (API Interactions), seguindo as
 * regras de resources/prompts/camara-sessao.md e o esquema de Article.
 * Em sobrecarga ou tempo esgotado, tenta de novo e depois o modelo seguinte.
 */
final class GeminiWriter
{
    /**
     * @param  list<string>  $models  em ordem de preferência
     */
    public function __construct(private string $apiKey, private array $models) {}

    /**
     * Só existe com a chave configurada (GEMINI_API_KEY).
     */
    public static function fromConfig(): ?self
    {
        $key = (string) config('itape.camara.gemini.key');

        return $key === '' ? null : new self($key, (array) config('itape.camara.gemini.models'));
    }

    /**
     * @param  Session  $session  já com os nomes curtos
     * @return array{model: string, data: array<mixed>} a resposta, ainda sem conferir, e o modelo que respondeu
     */
    public function write(Session $session): array
    {
        $errors = [];

        foreach ($this->models as $model) {
            $attempts = 1 + (int) config('itape.camara.gemini.retries');

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    return ['model' => $model, 'data' => $this->ask($model, $session)];
                } catch (TemporaryFailure $e) {
                    $errors[] = "{$model}: {$e->getMessage()}";

                    if ($attempt < $attempts) {
                        Sleep::for((int) config('itape.camara.gemini.retry_seconds'))->seconds();
                    }
                } catch (RuntimeException $e) {
                    // Resposta fora do formato: não adianta repetir no mesmo modelo.
                    $errors[] = "{$model}: {$e->getMessage()}";

                    break;
                }
            }
        }

        throw new RuntimeException(implode(' | ', $errors));
    }

    /**
     * @return array<mixed>
     */
    private function ask(string $model, Session $session): array
    {
        try {
            $response = Http::timeout((int) config('itape.camara.gemini.timeout'))
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Api-Revision' => (string) config('itape.camara.gemini.revision'),
                ])
                ->post((string) config('itape.camara.gemini.url'), [
                    'model' => $model,
                    'system_instruction' => (string) file_get_contents(resource_path('prompts/camara-sessao.md')),
                    'input' => "Dados da sessão (JSON):\n".json_encode($session->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => Article::schema(),
                    ],
                    'generation_config' => [
                        'thinking_level' => (string) config('itape.camara.gemini.thinking_level'),
                    ],
                    // Documentos públicos, mas não há por que o Google guardar a conversa.
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            throw new TemporaryFailure('sem resposta ('.mb_strimwidth($e->getMessage(), 0, 80, '…').')', previous: $e);
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new TemporaryFailure($this->describe($response));
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->describe($response));
        }

        if ($response->json('status') !== 'completed') {
            throw new RuntimeException('resposta incompleta (status: '.$response->json('status', '?').')');
        }

        foreach ((array) $response->json('steps', []) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach ($step['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'text') {
                    $data = json_decode((string) $part['text'], true);

                    return is_array($data) ? $data : throw new RuntimeException('JSON inválido na resposta');
                }
            }
        }

        throw new RuntimeException('resposta sem texto');
    }

    private function describe(Response $response): string
    {
        return 'HTTP '.$response->status().': '.mb_strimwidth((string) ($response->json('error.message') ?? $response->body()), 0, 160, '…');
    }
}
