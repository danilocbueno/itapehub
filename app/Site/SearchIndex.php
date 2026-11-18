<?php

namespace App\Site;

use App\Data\Source;

/**
 * Índice da busca (build/busca.json): uma entrada por notícia, da mais nova
 * para a mais antiga. A busca roda no navegador (site.js), que baixa o arquivo
 * só na primeira vez que o leitor busca. Chaves curtas para o arquivo ficar
 * pequeno: t = título, s = resumo, f = fonte, d = data ("AAAA-MM-DD HH:MM"),
 * u = link e i = 1 quando a notícia é do próprio site (abre na mesma aba).
 */
final class SearchIndex
{
    /**
     * @param  list<Cluster>  $clusters
     * @param  array<string, Source>  $sources
     * @return list<array{t: string, s: string, f: string, d: string, u: string, i?: int}>
     */
    public static function entries(array $clusters, array $sources, string $siteUrl): array
    {
        $own = rtrim($siteUrl, '/').'/';
        $entries = [];

        foreach ($clusters as $cluster) {
            $item = $cluster->primary;
            $names = array_map(fn (string $key) => isset($sources[$key]) ? $sources[$key]->short : $key, $cluster->sources());
            $entry = [
                't' => $item->title,
                's' => $item->summary,
                'f' => implode(', ', $names),
                'd' => $item->date()->format('Y-m-d H:i'),
                'u' => $item->url,
            ];

            if (str_starts_with($item->url, $own)) {
                $entry['i'] = 1;
            }

            $entries[] = $entry;
        }

        return $entries;
    }
}
