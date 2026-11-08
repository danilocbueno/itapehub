<?php

namespace App\Programas;

use App\Camara\TemporaryFailure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Escreve a notícia de um episódio com o Gemini, que assiste ao próprio vídeo
 * pelo link do YouTube (sem baixar áudio nem transcrever). Tenta os modelos em
 * ordem; se todos falharem, lança a exceção com os erros (RateLimited se algum
 * deles recusou por cota).
 */
final class VideoWriter
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

        return $key === '' ? null : new self($key, (array) config('itape.programas.models'));
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array{model: string, data: array<mixed>} a resposta, ainda sem conferir
     */
    public function write(Program $program, array $episode): array
    {
        $errors = [];
        $limited = false;

        foreach ($this->models as $model) {
            try {
                return ['model' => $model, 'data' => $this->ask($model, $program, $episode)];
            } catch (RuntimeException $e) {
                $errors[] = "{$model}: {$e->getMessage()}";
                $limited = $limited || $e instanceof RateLimited;
            }
        }

        throw $limited ? new RateLimited(implode(' | ', $errors)) : new RuntimeException(implode(' | ', $errors));
    }

    /**
     * @param  array<string, mixed>  $episode
     * @return array<mixed>
     */
    private function ask(string $model, Program $program, array $episode): array
    {
        $input = VideoStory::input(
            $program,
            (string) $episode['title'],
            CarbonImmutable::parse((string) $episode['date'], 'America/Sao_Paulo'),
            (int) $episode['seconds'],
        );

        try {
            $response = Http::timeout((int) config('itape.programas.timeout'))
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Api-Revision' => (string) config('itape.camara.gemini.revision'),
                ])
                ->post((string) config('itape.camara.gemini.url'), [
                    'model' => $model,
                    'system_instruction' => (string) file_get_contents(resource_path('prompts/programa-video.md')),
                    'input' => [
                        $this->video($program, (string) $episode['video']),
                        ['type' => 'text', 'text' => "Dados do episódio (JSON):\n".json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)],
                    ],
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => VideoStory::schema(),
                    ],
                    'generation_config' => [
                        'thinking_level' => (string) config('itape.programas.thinking_level'),
                    ],
                    'store' => false,
                ]);
        } catch (ConnectionException $e) {
            throw new TemporaryFailure('sem resposta ('.mb_strimwidth($e->getMessage(), 0, 80, '…').')', previous: $e);
        }

        if ($response->status() === 429) {
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

    /**
     * O vídeo pelo link do YouTube. Com "fps" no programa, menos quadros por segundo:
     * nas rádios a imagem é só o estúdio, e um programa de 3h30 só cabe assim.
     *
     * @return array<string, mixed>
     */
    private function video(Program $program, string $id): array
    {
        $video = ['type' => 'video', 'uri' => 'https://www.youtube.com/watch?v='.$id];

        if ($program->fps !== null) {
            $video['processing'] = ['type' => 'static', 'fps' => $program->fps];
        }

        return $video;
    }

    private function describe(Response $response): string
    {
        return 'HTTP '.$response->status().': '.mb_strimwidth((string) ($response->json('error.message') ?? $response->body()), 0, 160, '…');
    }
}
