<?php

namespace App\Site;

use Carbon\CarbonImmutable;

/**
 * Resumo do dia para colar no WhatsApp: as notícias das últimas 24 h,
 * as mais repercutidas primeiro, com um único link para o site.
 */
final class Digest
{
    /**
     * @param  list<Cluster>  $clusters
     * @return list<Cluster>
     */
    public static function select(array $clusters, CarbonImmutable $now, int $limit = 8): array
    {
        $recent = array_values(array_filter($clusters, fn (Cluster $c) => $c->date() >= $now->subDay() && $c->date() <= $now));

        usort($recent, fn (Cluster $a, Cluster $b) => [count($b->sources()), $b->date()] <=> [count($a->sources()), $a->date()]);

        return array_slice($recent, 0, $limit);
    }

    /**
     * @param  list<Cluster>  $clusters  já selecionados
     * @param  callable(string): string  $sourceName  nome curto de uma fonte pela chave
     */
    public static function text(array $clusters, CarbonImmutable $now, string $siteUrl, callable $sourceName, ?string $siteName = null): string
    {
        $siteName ??= (string) config('itape.site.name');
        $lines = [
            '*'.$siteName.' · '.Timeline::shortDate($now).'*',
            'As principais notícias de Itapetininga nas últimas 24 horas',
            '',
        ];

        foreach ($clusters as $cluster) {
            // Asteriscos ligariam/desligariam o negrito do WhatsApp no meio do título.
            $title = str_replace('*', '', $cluster->primary->title);
            $sources = implode(', ', array_map($sourceName, $cluster->sources()));
            $lines[] = "• {$title} ({$sources})";
        }

        if ($clusters === []) {
            $lines[] = 'Nenhuma notícia nas últimas 24 horas.';
        }

        $lines[] = '';
        $lines[] = 'Leia tudo em: '.rtrim($siteUrl, '/').'/';

        return implode("\n", $lines);
    }
}
