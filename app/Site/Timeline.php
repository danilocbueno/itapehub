<?php

namespace App\Site;

use App\Data\Item;
use Carbon\CarbonImmutable;

final class Timeline
{
    private const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

    private const MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    /**
     * Agrupa as notícias visíveis em clusters, mais recentes primeiro.
     *
     * @param  list<Item>  $items
     * @return list<Cluster>
     */
    public static function clusters(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (! $item->hidden) {
                $groups[$item->cluster ?? $item->id()][] = $item;
            }
        }

        $clusters = [];

        foreach ($groups as $group) {
            usort($group, fn (Item $a, Item $b) => $a->date() <=> $b->date());
            $clusters[] = new Cluster(array_shift($group), $group);
        }

        usort($clusters, fn (Cluster $a, Cluster $b) => $b->date() <=> $a->date());

        return $clusters;
    }

    /**
     * @param  list<Cluster>  $clusters
     * @return array<string, list<Cluster>> chave AAAA-MM-DD
     */
    public static function byDay(array $clusters): array
    {
        $days = [];

        foreach ($clusters as $cluster) {
            $days[$cluster->date()->format('Y-m-d')][] = $cluster;
        }

        return $days;
    }

    public static function dayLabel(string $day, CarbonImmutable $now): string
    {
        $date = CarbonImmutable::parse($day, $now->getTimezone());

        return match (true) {
            $date->isSameDay($now) => 'Hoje',
            $date->isSameDay($now->subDay()) => 'Ontem',
            default => self::WEEKDAYS[$date->dayOfWeek].', '.$date->format('d/m'),
        };
    }

    /**
     * Rótulo compacto para a previsão: "Hoje", "Amanhã", "Ter 27".
     */
    public static function shortDay(string $day, CarbonImmutable $now): string
    {
        $date = CarbonImmutable::parse($day, $now->getTimezone());

        return match (true) {
            $date->isSameDay($now) => 'Hoje',
            $date->isSameDay($now->addDay()) => 'Amanhã',
            default => mb_substr(self::WEEKDAYS[$date->dayOfWeek], 0, 3).' '.$date->format('d'),
        };
    }

    /**
     * Rótulo de dia da agenda: "Hoje", "Amanhã", "Sábado, 31/10".
     */
    public static function agendaDay(string $day, CarbonImmutable $now): string
    {
        $date = CarbonImmutable::parse($day, $now->getTimezone());

        return match (true) {
            $date->isSameDay($now) => 'Hoje',
            $date->isSameDay($now->addDay()) => 'Amanhã',
            default => self::WEEKDAYS[$date->dayOfWeek].', '.$date->format('d/m'),
        };
    }

    /**
     * "Sáb", "Ter".
     */
    public static function weekdayShort(CarbonImmutable $date): string
    {
        return mb_substr(self::WEEKDAYS[$date->dayOfWeek], 0, 3);
    }

    /**
     * "out", "nov".
     */
    public static function monthShort(CarbonImmutable $date): string
    {
        return mb_substr(self::MONTHS[$date->month], 0, 3);
    }

    /**
     * "sexta, 30/10".
     */
    public static function shortDate(CarbonImmutable $date): string
    {
        return mb_strtolower(self::WEEKDAYS[$date->dayOfWeek]).', '.$date->format('d/m');
    }

    /**
     * "Domingo, 25 de outubro" (linha de data da home).
     */
    public static function dateline(CarbonImmutable $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek].', '.$date->day.' de '.self::MONTHS[$date->month];
    }

    public static function longDate(CarbonImmutable $date): string
    {
        return mb_strtolower(self::WEEKDAYS[$date->dayOfWeek]).', '.$date->day.' de '.self::MONTHS[$date->month].' de '.$date->year;
    }

    public static function monthLabel(string $yearMonth): string
    {
        [$year, $month] = array_map('intval', explode('-', $yearMonth));

        return ucfirst(self::MONTHS[$month]).' de '.$year;
    }
}
