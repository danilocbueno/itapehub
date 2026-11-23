<?php

namespace App\Resumo;

use App\Data\Item;
use App\Data\Source;
use App\Site\Timeline;
use Carbon\CarbonImmutable;

/**
 * Os resumos publicados (data/resumos) prontos para o site: a página de cada
 * dia em /resumo/AAAA/MM/DD/, um item na linha do tempo (no fim do dia) e o
 * item do RSS.
 */
final class DaySummaries
{
    public const SOURCE = 'resumo-do-dia';

    /**
     * "Fonte" usada no chapéu do card. Não é coletada nem aparece em /fontes/.
     */
    public static function source(string $siteUrl): Source
    {
        return new Source(
            key: self::SOURCE,
            name: 'Resumo do dia',
            short: 'Resumo do dia',
            type: 'rss',
            url: rtrim($siteUrl, '/').'/',
            homepage: rtrim($siteUrl, '/').'/',
            color: '#b42318',
            enabled: false,
        );
    }

    /**
     * @return list<array{path: string, date: CarbonImmutable, summary: array<string, mixed>, item: Item}> mais recentes primeiro
     */
    public static function load(string $directory, string $siteUrl): array
    {
        $all = [];

        foreach (glob($directory.'/*.json') ?: [] as $file) {
            $summary = json_decode((string) file_get_contents($file), true);

            if (! is_array($summary) || ! in_array($summary['kind'] ?? null, ['ia', 'lista'], true) || ($summary['news'] ?? []) === []) {
                continue;
            }

            $date = CarbonImmutable::parse((string) $summary['date'], 'America/Sao_Paulo');
            $path = 'resumo/'.$date->format('Y/m/d').'/';
            $count = count($summary['news']);
            $images = array_filter(array_column($summary['news'], 'image'));

            $all[] = [
                'path' => $path,
                'date' => $date,
                'summary' => $summary,
                'item' => new Item(
                    source: self::SOURCE,
                    url: rtrim($siteUrl, '/').'/'.$path,
                    title: $summary['kind'] === 'ia'
                        ? (string) $summary['article']['titulo']
                        : 'Resumo de '.Timeline::shortDate($date).': as notícias do dia em Itapetininga',
                    summary: $summary['kind'] === 'ia'
                        ? (string) $summary['article']['linha_fina']
                        : $count.($count === 1 ? ' notícia' : ' notícias').' do dia, das mais repercutidas para as demais.',
                    image: $images === [] ? null : (string) reset($images),
                    // No fim do dia, para o resumo abrir a lista daquele dia na linha do tempo.
                    publishedAt: $date->setTime(23, 59),
                ),
            ];
        }

        usort($all, fn (array $a, array $b) => $b['date'] <=> $a['date']);

        return $all;
    }
}
