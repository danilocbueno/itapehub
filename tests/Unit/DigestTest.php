<?php

use App\Data\Item;
use App\Site\Cluster;
use App\Site\Digest;
use Carbon\CarbonImmutable;

function clusterOf(string $title, string $date, array $sources = ['jornal']): Cluster
{
    $items = array_map(
        fn (string $source) => new Item($source, "https://{$source}.test/".md5($title), $title, publishedAt: CarbonImmutable::parse($date, 'America/Sao_Paulo')),
        $sources,
    );

    return new Cluster(array_shift($items), $items);
}

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-10-30 18:00', 'America/Sao_Paulo');
});

it('seleciona só as notícias das últimas 24 horas', function () {
    $selected = Digest::select([
        clusterOf('Recente', '2026-10-30 10:00'),
        clusterOf('Ontem à noite', '2026-10-29 19:00'),
        clusterOf('Antiga', '2026-10-29 17:00'),
    ], $this->now);

    expect(array_map(fn (Cluster $c) => $c->primary->title, $selected))->toBe(['Recente', 'Ontem à noite']);
});

it('coloca primeiro as notícias publicadas por mais fontes', function () {
    $selected = Digest::select([
        clusterOf('Só no Jornal', '2026-10-30 17:00'),
        clusterOf('Em três fontes', '2026-10-30 09:00', ['tvsmais', 'g1', 'correio']),
        clusterOf('Em duas fontes', '2026-10-30 12:00', ['correio', 'jornal']),
    ], $this->now);

    expect(array_map(fn (Cluster $c) => $c->primary->title, $selected))->toBe(['Em três fontes', 'Em duas fontes', 'Só no Jornal']);
});

it('desempata pelas mais recentes e limita a quantidade', function () {
    $clusters = array_map(fn (int $h) => clusterOf("Notícia das {$h}h", sprintf('2026-10-30 %02d:00', $h)), range(1, 12));

    $selected = Digest::select($clusters, $this->now, limit: 3);

    expect(array_map(fn (Cluster $c) => $c->primary->title, $selected))->toBe(['Notícia das 12h', 'Notícia das 11h', 'Notícia das 10h']);
});

it('monta o texto no formato do WhatsApp com um link só para o site', function () {
    $text = Digest::text([
        clusterOf('Colisão interdita rodovia em Itapetininga', '2026-10-30 16:00', ['tvsmais', 'g1']),
        clusterOf('PAT tem 266 vagas de emprego', '2026-10-30 16:13', ['prefeitura']),
    ], $this->now, 'https://danilocbueno.github.io/itapenews', fn (string $key) => ['tvsmais' => 'TVS+', 'g1' => 'g1', 'prefeitura' => 'Prefeitura'][$key]);

    expect($text)->toBe(
        "*ItapêHub · sexta, 30/10*\n"
        ."As principais notícias de Itapetininga nas últimas 24 horas\n\n"
        ."• Colisão interdita rodovia em Itapetininga (TVS+, g1)\n"
        ."• PAT tem 266 vagas de emprego (Prefeitura)\n\n"
        .'Leia tudo em: https://danilocbueno.github.io/itapenews/'
    );
});

it('remove asteriscos dos títulos para não quebrar o negrito do WhatsApp', function () {
    $text = Digest::text([clusterOf('Show *imperdível* no sábado', '2026-10-30 10:00')], $this->now, 'https://x.test', fn () => 'Jornal');

    expect($text)->toContain('• Show imperdível no sábado (Jornal)');
});

it('avisa quando não há notícias nas últimas 24 horas', function () {
    $text = Digest::text([], $this->now, 'https://x.test', fn () => '');

    expect($text)->toContain('Nenhuma notícia nas últimas 24 horas.')
        ->toEndWith('Leia tudo em: https://x.test/');
});

describe('Resumo do dia na home', function () {
    it('usa só as notícias de hoje, desde a meia-noite', function () {
        $summary = Digest::today([
            clusterOf('Hoje cedo', '2026-10-30 07:00'),
            clusterOf('Ontem à noite', '2026-10-29 22:00'),
        ], $this->now);

        expect($summary['total'])->toBe(1)
            ->and($summary['highlights'][0]->primary->title)->toBe('Hoje cedo');
    });

    it('destaca as três mais repercutidas e agrupa o resto por período, o mais recente primeiro', function () {
        $summary = Digest::today([
            clusterOf('Em três fontes', '2026-10-30 09:00', ['tvsmais', 'g1', 'correio']),
            clusterOf('Em duas fontes', '2026-10-30 12:00', ['correio', 'jornal']),
            clusterOf('Mais recente', '2026-10-30 17:30'),
            clusterOf('Da tarde', '2026-10-30 14:00'),
            clusterOf('Da manhã', '2026-10-30 08:00'),
            clusterOf('Da madrugada', '2026-10-30 03:00'),
        ], $this->now);

        expect(array_map(fn (Cluster $c) => $c->primary->title, $summary['highlights']))->toBe(['Em três fontes', 'Em duas fontes', 'Mais recente'])
            ->and(array_column($summary['periods'], 'label'))->toBe(['Tarde', 'Manhã', 'Madrugada'])
            ->and($summary['periods'][0]['clusters'][0]->primary->title)->toBe('Da tarde')
            ->and($summary['total'])->toBe(6);
    });

    it('não cria períodos quando todas as notícias cabem nos destaques', function () {
        $summary = Digest::today([clusterOf('Única', '2026-10-30 10:00')], $this->now);

        expect($summary['periods'])->toBe([]);
    });

    it('classifica o período pela hora', function (string $time, string $label) {
        expect(Digest::period(CarbonImmutable::parse("2026-10-30 {$time}")))->toBe($label);
    })->with([
        ['05:59', 'Madrugada'],
        ['06:00', 'Manhã'],
        ['12:00', 'Tarde'],
        ['18:00', 'Noite'],
        ['23:59', 'Noite'],
    ]);
});
