<?php

namespace App\Cinema;

use Carbon\CarbonImmutable;

/**
 * Prepara a programação guardada em data/cinema.json para o site.
 */
final class Showtimes
{
    /**
     * Dias de hoje em diante, com a data como objeto e cada horário marcado se já passou.
     *
     * @param  array<string, mixed>|null  $cinema  conteúdo de data/cinema.json
     * @return list<array{date: CarbonImmutable, films: list<array<string, mixed>>}>
     */
    public static function upcoming(?array $cinema, CarbonImmutable $now): array
    {
        $days = [];

        foreach ($cinema['days'] ?? [] as $day) {
            $date = CarbonImmutable::parse($day['date'], $now->getTimezone());

            if ($date->lt($now->startOfDay())) {
                continue;
            }

            $films = [];

            foreach ($day['films'] as $film) {
                foreach ($film['sessions'] as $i => $session) {
                    foreach ($session['times'] as $j => $time) {
                        $film['sessions'][$i]['times'][$j]['past'] = $date->setTimeFromTimeString($time['time'])->lt($now);
                    }
                }

                $films[] = $film;
            }

            $days[] = ['date' => $date, 'films' => $films];
        }

        return $days;
    }

    /**
     * Para o bloco da Agenda: as sessões que ainda vão começar hoje ou, se o dia
     * já acabou, as do próximo dia com programação. Só os filmes com horário sobrando.
     *
     * @param  list<array{date: CarbonImmutable, films: list<array<string, mixed>>}>  $days  de upcoming()
     * @return array{date: CarbonImmutable, films: list<array{title: string, url: string|null, poster: string|null, tag: string, info: string, times: list<array{time: string, url: string}>}>}|null
     */
    public static function next(array $days): ?array
    {
        foreach ($days as $day) {
            $films = [];

            foreach ($day['films'] as $film) {
                $times = [];
                $infos = [];

                foreach ($film['sessions'] as $session) {
                    $left = array_values(array_filter($session['times'], fn (array $t) => ! $t['past']));

                    if ($left !== []) {
                        $times = [...$times, ...$left];
                        $infos[] = $session['info'];
                    }
                }

                if ($times !== []) {
                    usort($times, fn (array $a, array $b) => strcmp(str_pad($a['time'], 5, '0', STR_PAD_LEFT), str_pad($b['time'], 5, '0', STR_PAD_LEFT)));
                    $films[] = [
                        'title' => (string) $film['title'],
                        'url' => $film['url'] ?? null,
                        'poster' => $film['poster'] ?? null,
                        'tag' => (string) ($film['tag'] ?? ''),
                        'info' => implode(' / ', array_unique($infos)),
                        'times' => $times,
                    ];
                }
            }

            if ($films !== []) {
                return ['date' => $day['date'], 'films' => $films];
            }
        }

        return null;
    }
}
