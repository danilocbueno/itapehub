<?php

namespace App\Camara;

use App\Data\Item;
use App\Data\Source;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * As notícias das sessões (data/camara/noticias) prontas para o site: um item
 * para a linha do tempo, que leva à página própria da sessão em /camara/<slug>/.
 */
final class SessionNews
{
    public const SOURCE = 'camara-sessoes';

    /**
     * "Fonte" usada no chapéu do card. Não é coletada nem aparece em /fontes/.
     */
    public static function source(): Source
    {
        return new Source(
            key: self::SOURCE,
            name: 'Sessões da Câmara',
            short: 'Sessão da Câmara',
            // O tipo não é usado: essas notícias não passam pela coleta.
            type: 'rss',
            url: (string) config('itape.camara.url'),
            homepage: (string) config('itape.camara.url'),
            color: '#475569',
            enabled: false,
        );
    }

    /**
     * @return list<array{slug: string, item: Item, news: array<string, mixed>}> mais recentes primeiro
     */
    public static function load(string $directory, string $siteUrl): array
    {
        $all = [];

        foreach (glob($directory.'/*.json') ?: [] as $file) {
            $news = json_decode((string) file_get_contents($file), true);

            if (! is_array($news) || ! isset($news['article']['titulo'], $news['title'], $news['date'])) {
                continue;
            }

            $slug = Str::slug((string) $news['title']);
            $time = preg_match('~^\d{1,2}:\d{2}$~', (string) ($news['closed_at'] ?? '')) ? $news['closed_at'] : '21:00';

            $all[] = [
                'slug' => $slug,
                'news' => $news,
                'item' => new Item(
                    source: self::SOURCE,
                    url: rtrim($siteUrl, '/').'/camara/'.$slug.'/',
                    title: (string) $news['article']['titulo'],
                    summary: (string) ($news['article']['linha_fina'] ?? ''),
                    publishedAt: CarbonImmutable::parse($news['date'].' '.$time, 'America/Sao_Paulo'),
                ),
            ];
        }

        usort($all, fn (array $a, array $b) => $b['item']->date() <=> $a['item']->date());

        return $all;
    }
}
