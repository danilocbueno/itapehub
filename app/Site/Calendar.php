<?php

namespace App\Site;

use Carbon\CarbonImmutable;

/**
 * Calendário de um mês para o arquivo: semanas de domingo a sábado, com a
 * quantidade de notícias de cada dia.
 */
final class Calendar
{
    public const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /**
     * @param  string  $yearMonth  AAAA-MM
     * @param  array<string, int>  $counts  notícias por dia (chave AAAA-MM-DD)
     * @return list<list<array{day: int, date: string, count: int}|null>> null = fora do mês
     */
    public static function weeks(string $yearMonth, array $counts): array
    {
        $first = CarbonImmutable::parse($yearMonth.'-01');
        $cells = array_fill(0, $first->dayOfWeek, null);

        for ($day = 1; $day <= $first->daysInMonth; $day++) {
            $date = $first->setDay($day)->format('Y-m-d');
            $cells[] = ['day' => $day, 'date' => $date, 'count' => $counts[$date] ?? 0];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }
}
