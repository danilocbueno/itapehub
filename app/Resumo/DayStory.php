<?php

namespace App\Resumo;

use App\Programas\VideoStory;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * O resumo de um dia escrito pelo Gemini (regras em
 * resources/prompts/resumo-do-dia.md), conferido antes de publicar: tamanhos,
 * cada bloco apontando para notícias que existem naquele dia, nenhum dado de
 * contato no texto e o dia da semana certo.
 */
final class DayStory
{
    /**
     * Esquema da resposta (response_format da API Interactions).
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $text = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'titulo' => $text,
                'linha_fina' => $text,
                'lide' => $text,
                'blocos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'intertitulo' => $text,
                            'paragrafos' => ['type' => 'array', 'items' => $text],
                            'noticias' => ['type' => 'array', 'items' => $text],
                        ],
                        'required' => ['intertitulo', 'paragrafos', 'noticias'],
                    ],
                ],
            ],
            'required' => ['titulo', 'linha_fina', 'lide', 'blocos'],
        ];
    }

    /**
     * Dados de entrada para o Gemini: a data por extenso e as notícias do dia,
     * as mais repercutidas primeiro.
     *
     * @param  list<array<string, mixed>>  $news  ver DayDesk::snapshot()
     * @return array<string, mixed>
     */
    public static function input(CarbonImmutable $date, array $news): array
    {
        return [
            'data' => VideoStory::fullDate($date),
            'noticias' => array_map(fn (array $n) => [
                'id' => $n['id'],
                'hora' => $n['time'],
                'veiculos' => $n['sources'],
                'titulo' => $n['title'],
                'resumo' => $n['summary'],
            ], $news),
        ];
    }

    /**
     * Confere a resposta e devolve o resumo pronto para gravar.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $ids  ids das notícias enviadas
     * @return array{titulo: string, linha_fina: string, lide: string, blocos: list<array{intertitulo: string, paragrafos: list<string>, noticias: list<string>}>}
     */
    public static function check(array $data, array $ids, CarbonImmutable $date): array
    {
        $article = [
            'titulo' => VideoStory::text($data, 'titulo', 120),
            'linha_fina' => VideoStory::text($data, 'linha_fina', 240),
            'lide' => VideoStory::text($data, 'lide', 1000),
            'blocos' => self::blocks($data['blocos'] ?? null, $ids),
        ];

        $body = implode("\n", [
            $article['titulo'], $article['linha_fina'], $article['lide'],
            ...array_merge(...array_map(fn (array $b) => [$b['intertitulo'], ...$b['paragrafos']], $article['blocos'])),
        ]);

        VideoStory::checkContacts($body);
        VideoStory::checkWeekday($body, $date);

        return $article;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{intertitulo: string, paragrafos: list<string>, noticias: list<string>}>
     */
    private static function blocks(mixed $blocks, array $ids): array
    {
        if (! is_array($blocks) || count($blocks) < 1 || count($blocks) > 6) {
            throw new InvalidArgumentException('O resumo precisa ter de um a seis blocos.');
        }

        $result = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException('Bloco inválido.');
            }

            $paragraphs = array_values(array_filter(array_map(fn ($p) => trim((string) $p), (array) ($block['paragrafos'] ?? [])), fn (string $p) => $p !== ''));

            if (count($paragraphs) < 1 || count($paragraphs) > 3 || max(array_map('mb_strlen', $paragraphs)) > 800) {
                throw new InvalidArgumentException('Cada bloco precisa ter de um a três parágrafos curtos.');
            }

            // Cada bloco diz de quais notícias saiu: é o que o leitor usa para conferir.
            $news = array_values(array_unique(array_map(fn ($id) => trim((string) $id), (array) ($block['noticias'] ?? []))));
            $unknown = array_diff($news, $ids);

            if ($news === [] || $unknown !== []) {
                throw new InvalidArgumentException('Bloco sem notícia de origem ou com notícia que não existe: '.implode(', ', $unknown ?: ['nenhuma']));
            }

            $result[] = [
                'intertitulo' => VideoStory::text($block, 'intertitulo', 80, false),
                'paragrafos' => $paragraphs,
                'noticias' => $news,
            ];
        }

        return $result;
    }
}
