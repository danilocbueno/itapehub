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
     * Resumo do dia da home: as notícias de hoje (desde a meia-noite), com os
     * destaques no topo e as demais por período, o mais recente primeiro.
     *
     * @param  list<Cluster>  $clusters
     * @return array{total: int, highlights: list<Cluster>, periods: list<array{label: string, clusters: list<Cluster>}>}
     */
    public static function today(array $clusters, CarbonImmutable $now, int $highlights = 3): array
    {
        $start = $now->startOfDay();
        $today = array_values(array_filter($clusters, fn (Cluster $c) => $c->date() >= $start && $c->date() <= $now));
        usort($today, fn (Cluster $a, Cluster $b) => $b->date() <=> $a->date());

        $top = self::select($today, $now, $highlights);
        $rest = array_values(array_filter($today, fn (Cluster $c) => ! in_array($c, $top, true)));

        $periods = [];

        foreach ($rest as $cluster) {
            $label = self::period($cluster->date()->setTimezone($now->getTimezone()));
            $last = array_key_last($periods);

            if ($last === null || $periods[$last]['label'] !== $label) {
                $periods[] = ['label' => $label, 'clusters' => []];
                $last = array_key_last($periods);
            }

            $periods[$last]['clusters'][] = $cluster;
        }

        return ['total' => count($today), 'highlights' => $top, 'periods' => $periods];
    }

    public static function period(CarbonImmutable $date): string
    {
        return match (true) {
            $date->hour < 6 => 'Madrugada',
            $date->hour < 12 => 'Manhã',
            $date->hour < 18 => 'Tarde',
            default => 'Noite',
        };
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
