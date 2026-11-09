<?php

namespace App\Cinema;

use Carbon\CarbonImmutable;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Lê a programação do Grupo Cine (grupocine.com.br/<cidade>/programacao). A
 * página tem uma aba por dia ("Hoje 09 Nov") e, em cada aba, os filmes com as
 * sessões (sala, idioma, formato) e os horários com link de compra.
 */
final class CinemaParser
{
    private const MONTHS = ['jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12];

    /**
     * @return list<array{date: string, films: list<array<string, mixed>>}> em ordem de data
     */
    public static function days(string $html, CarbonImmutable $now): array
    {
        $crawler = new Crawler($html);
        $labels = [];

        // Abas: data-tab="tab0" com dia ("09") e mês ("Nov").
        $crawler->filter('.date-tab[data-tab]')->each(function (Crawler $tab) use (&$labels, $now) {
            $day = (int) trim($tab->filter('.day')->text(''));
            $month = self::MONTHS[mb_strtolower(mb_substr(trim($tab->filter('.month')->text('')), 0, 3))] ?? 0;

            if ($day > 0 && $month > 0) {
                $labels[(string) $tab->attr('data-tab')] = self::date($day, $month, $now);
            }
        });

        if ($labels === []) {
            throw new RuntimeException('programação sem as abas de dias (o formato mudou?)');
        }

        $days = [];

        $crawler->filter('.tab-pane[id]')->each(function (Crawler $pane) use (&$days, $labels) {
            $date = $labels[(string) $pane->attr('id')] ?? null;

            if ($date === null) {
                return;
            }

            $films = $pane->filter('.filme-box-content')->each(fn (Crawler $film) => self::film($film));
            $films = array_values(array_filter($films, fn (array $f) => $f['title'] !== '' && $f['sessions'] !== []));

            if ($films !== []) {
                $days[] = ['date' => $date, 'films' => $films];
            }
        });

        usort($days, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        return $days;
    }

    /**
     * @return array<string, mixed>
     */
    private static function film(Crawler $film): array
    {
        $info = $film->filter('.info-filme-content span.text-grey')->each(fn (Crawler $span) => trim($span->text('')));
        $field = function (string $label) use ($info): string {
            foreach ($info as $text) {
                if (str_starts_with($text, $label.':')) {
                    return trim(substr($text, strlen($label) + 1));
                }
            }

            return '';
        };
        $link = $film->filter('a[href*="/filme/"]');
        $poster = $film->filter('img.poster-filme');

        return [
            'title' => trim($film->filter('.titulo-filme')->text('')),
            'url' => $link->count() ? (string) $link->attr('href') : null,
            'poster' => $poster->count() ? (string) $poster->attr('src') : null,
            // "Estreia", "Pré-venda"…
            'tag' => trim($film->filter('.tag')->text('')),
            'rating' => trim($film->filter('.classificacao-box')->text('')),
            'genre' => $field('Gênero'),
            'minutes' => (int) $field('Duração') ?: null,
            'sessions' => $film->filter('.horarios-box')->each(fn (Crawler $box) => [
                // "Sala 1 · Nacional · 2D"
                'info' => trim(preg_replace('~\s+~u', ' ', $box->filter('.info-sessao')->text('')) ?? ''),
                'times' => array_values(array_filter($box->filter('a.btn-horario')->each(fn (Crawler $a) => [
                    'time' => trim($a->filter('.fw-bold')->text('')),
                    'url' => (string) $a->attr('href'),
                ]), fn (array $t) => preg_match('~^\d{1,2}:\d{2}$~', $t['time']) === 1)),
            ]),
        ];
    }

    /**
     * Dia e mês da aba → data. A página não traz o ano: é o ano que deixa a data
     * mais perto de hoje (a programação de dezembro vista em janeiro é do ano anterior).
     */
    private static function date(int $day, int $month, CarbonImmutable $now): string
    {
        $candidates = array_map(fn (int $year) => CarbonImmutable::create($year, $month, $day, 0, 0, 0, $now->getTimezone()), [$now->year - 1, $now->year, $now->year + 1]);
        usort($candidates, fn ($a, $b) => abs($a->diffInDays($now)) <=> abs($b->diffInDays($now)));

        return $candidates[0]->format('Y-m-d');
    }
}
