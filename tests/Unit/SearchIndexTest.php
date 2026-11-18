<?php

use App\Data\Item;
use App\Data\Source;
use App\Site\Cluster;
use App\Site\SearchIndex;
use Carbon\CarbonImmutable;

it('monta a entrada com título, resumo, fontes do card, data e link', function () {
    $sources = Source::all();
    $key = array_key_first($sources);
    $other = array_keys($sources)[1];
    $cluster = new Cluster(
        new Item($key, 'https://fonte.test/a', 'Título', 'Resumo', publishedAt: CarbonImmutable::parse('2026-11-10 14:30', 'America/Sao_Paulo')),
        [new Item($other, 'https://outra.test/a', 'Título')],
    );

    expect(SearchIndex::entries([$cluster], $sources, 'https://itapehub.com'))->toBe([[
        't' => 'Título',
        's' => 'Resumo',
        'f' => $sources[$key]->short.', '.$sources[$other]->short,
        'd' => '2026-11-10 14:30',
        'u' => 'https://fonte.test/a',
    ]]);
});

it('marca as notícias do próprio site, que abrem na mesma aba', function () {
    $item = new Item('camara-noticias', 'https://itapehub.com/camara/sessao-1/', 'Sessão', publishedAt: CarbonImmutable::parse('2026-11-10 19:00'));

    expect(SearchIndex::entries([new Cluster($item)], [], 'https://itapehub.com/')[0])
        ->toMatchArray(['f' => 'camara-noticias', 'i' => 1]);
});
