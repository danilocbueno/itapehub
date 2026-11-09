<?php

use App\Cinema\CinemaParser;
use App\Cinema\Showtimes;
use Carbon\CarbonImmutable;

function cinemaNow(string $at = '2026-11-09 10:00'): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'America/Sao_Paulo');
}

it('lê a programação do Grupo Cine com filmes, sessões e horários', function () {
    $days = CinemaParser::days(fixtureContents('grupocine-programacao.html'), cinemaNow());
    $film = $days[0]['films'][0];

    expect($days)->toHaveCount(26)
        ->and($days[0]['date'])->toBe('2026-11-09')
        ->and(end($days)['date'])->toBe('2026-12-23')
        ->and(array_column($days[0]['films'], 'title'))->toContain('Os Farofeiros 3', 'Godzilla Minus Zero')
        ->and($film)->toMatchArray([
            'title' => 'Os Farofeiros 3',
            'url' => 'https://www.grupocine.com.br/filme/16672/os-farofeiros-3',
            'poster' => 'https://static3.moviehub.com.br/fotos/filmes/poster/16672_medio.jpg',
            'tag' => 'Estreia',
            'rating' => '12',
            'genre' => 'Comédia',
            'minutes' => 103,
        ])
        ->and($film['sessions'][0]['info'])->toBe('Sala 1 · Nacional · 2D')
        ->and(array_column($film['sessions'][0]['times'], 'time'))->toBe(['14:50', '17:05', '19:20', '21:35'])
        ->and($film['sessions'][0]['times'][0]['url'])->toStartWith('https://www.grupocine.com.br/venda/');
});

it('deduz o ano das abas, inclusive na virada do ano', function () {
    // Vista em janeiro, a aba "23 Dez" é do ano anterior.
    $days = CinemaParser::days(fixtureContents('grupocine-programacao.html'), cinemaNow('2027-01-02 10:00'));

    expect(end($days)['date'])->toBe('2026-12-23');
});

it('acusa a página sem as abas de dias', function () {
    CinemaParser::days('<html><body>Em manutenção</body></html>', cinemaNow());
})->throws(RuntimeException::class, 'abas de dias');

/**
 * @return array<string, mixed> conteúdo de data/cinema.json com dois dias
 */
function cinemaData(): array
{
    $film = fn (string $title, array $times) => [
        'title' => $title, 'url' => null, 'poster' => null, 'tag' => '', 'rating' => 'L', 'genre' => 'Animação', 'minutes' => 90,
        'sessions' => [['info' => 'Sala 2 · Dublado · 2D', 'times' => array_map(fn ($t) => ['time' => $t, 'url' => 'https://cine/'.$t], $times)]],
    ];

    return ['days' => [
        ['date' => '2026-11-08', 'films' => [$film('Ontem', ['20:00'])]],
        ['date' => '2026-11-09', 'films' => [$film('Matinê', ['14:00']), $film('Noite', ['14:30', '21:00'])]],
        ['date' => '2026-11-10', 'films' => [$film('Amanhã', ['15:00'])]],
    ]];
}

it('mostra só os dias de hoje em diante e marca as sessões que já começaram', function () {
    $days = Showtimes::upcoming(cinemaData(), cinemaNow('2026-11-09 18:00'));

    expect($days)->toHaveCount(2)
        ->and($days[0]['date']->format('Y-m-d'))->toBe('2026-11-09')
        ->and($days[0]['films'][1]['sessions'][0]['times'][0]['past'])->toBeTrue()
        ->and($days[0]['films'][1]['sessions'][0]['times'][1]['past'])->toBeFalse();
});

it('escolhe para a Agenda as sessões que ainda vão começar hoje', function () {
    $next = Showtimes::next(Showtimes::upcoming(cinemaData(), cinemaNow('2026-11-09 18:00')));

    expect($next['date']->format('Y-m-d'))->toBe('2026-11-09')
        ->and(array_column($next['films'], 'title'))->toBe(['Noite'])
        ->and(array_column($next['films'][0]['times'], 'time'))->toBe(['21:00']);
});

it('passa para o dia seguinte quando as sessões de hoje acabaram', function () {
    $next = Showtimes::next(Showtimes::upcoming(cinemaData(), cinemaNow('2026-11-09 22:00')));

    expect($next['date']->format('Y-m-d'))->toBe('2026-11-10')
        ->and(array_column($next['films'], 'title'))->toBe(['Amanhã'])
        ->and(Showtimes::next(Showtimes::upcoming(null, cinemaNow())))->toBeNull();
});
