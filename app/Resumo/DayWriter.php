<?php

namespace App\Resumo;

use App\Camara\TemporaryFailure;
use App\Programas\RateLimited;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Escreve o resumo do dia com o Gemini (só texto: títulos e resumos das
 * notícias do dia). Tenta os modelos em ordem; se todos falharem, lança a
 * exceção com os erros (RateLimited se algum recusou por cota ou sobrecarga).
 */
final class DayWriter
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

        return $key === '' ? null : new self($key, array_values((array) config('itape.resumo.models')));
    }

    /**
     * @param  list<array<string, mixed>>  $news
     * @return array{model: string, data: array<mixed>} a resposta, ainda sem conferir, e o modelo que respondeu
     */
    public function write(CarbonImmutable $date, array $news): array
    {
        $errors = [];
        $limited = false;

        foreach ($this->models as $model) {
            try {
                return ['model' => $model, 'data' => $this->ask($model, $date, $news)];
            } catch (RuntimeException $e) {
                $errors[] = "{$model}: {$e->getMessage()}";
                $limited = $limited || $e instanceof RateLimited;
            }
        }

        $message = implode(' | ', $errors);

        throw $limited ? new RateLimited($message) : new RuntimeException($message);
    }

    /**
     * @param  list<array<string, mixed>>  $news
     * @return array<mixed>
     */
    private function ask(string $model, CarbonImmutable $date, array $news): array
    {
        try {
            $response = Http::timeout((int) config('itape.resumo.timeout'))
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Api-Revision' => (string) config('itape.camara.gemini.revision'),
                ])
                ->post((string) config('itape.camara.gemini.url'), [
                    'model' => $model,
                    'system_instruction' => (string) file_get_contents(resource_path('prompts/resumo-do-dia.md')),
                    'input' => "Dados do dia (JSON):\n".json_encode(DayStory::input($date, $news), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => DayStory::schema(),
                    ],
                    'generation_config' => [
                        'thinking_level' => (string) config('itape.resumo.thinking_level'),
                    ],
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            throw new TemporaryFailure('sem resposta ('.mb_strimwidth($e->getMessage(), 0, 80, '…').')', previous: $e);
        }

        // Cota (429) ou modelo sobrecarregado (503): recusa do Google, não do pedido.
        if (in_array($response->status(), [429, 503], true)) {
            throw new RateLimited($this->describe($response));
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
