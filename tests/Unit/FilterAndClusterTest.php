<?php

use App\Data\Item;
use App\Pipeline\Clusterer;
use App\Pipeline\LocalFilter;
use App\Sources\RssAdapter;
use Carbon\CarbonImmutable;

function item(string $source, string $title, string $summary = '', string $date = '2026-10-25 10:00'): Item
{
    return new Item(
        source: $source,
        url: 'https://example.com/'.md5($source.$title),
        title: $title,
        summary: $summary,
        publishedAt: CarbonImmutable::parse($date, 'America/Sao_Paulo'),
    );
}

describe('Filtro "Itapetininga"', function () {
    it('aceita tudo de fontes locais', function () {
        expect((new LocalFilter)->accepts(item('jornal', 'Feira em Tatuí'), source('jornal')))->toBeTrue();
    });

    it('não se deixa enganar pela chamada fixa do g1', function () {
        $items = (new RssAdapter)->parse(fixtureContents('g1.xml'), source('g1'));
        $itarare = array_values(array_filter($items, fn (Item $item) => str_contains($item->title, 'Itararé abre pré-seleção')))[0];

        expect((new LocalFilter)->accepts($itarare, source('g1')))->toBeFalse();
    });

    it('aceita notícias regionais sobre Itapetininga', function () {
        $item = item('g1', 'Temporal causa alagamentos na região', 'Em Itapetininga, o rio transbordou.');

        expect((new LocalFilter)->accepts($item, source('g1')))->toBeTrue();
    });

    it('ignora menção a Itapetininga só no fim do texto', function () {
        $item = item('g1', 'Feira em Capão Bonito', str_repeat('Texto sobre Capão Bonito. ', 40).'Fica perto de Itapetininga.');

        expect((new LocalFilter)->accepts($item, source('g1')))->toBeFalse();
    });

    it('filtra uma parte relevante do g1 real', function () {
        $items = (new RssAdapter)->parse(fixtureContents('g1.xml'), source('g1'));
        $accepted = array_filter($items, fn (Item $item) => (new LocalFilter)->accepts($item, source('g1')));

        expect(count($accepted))->toBeGreaterThan(10)->toBeLessThan(50);
    });
});

describe('Agrupamento', function () {
    it('junta a mesma notícia em fontes diferentes', function (string $a, string $b) {
        expect((new Clusterer)->similar($a, $b))->toBeTrue();
    })->with([
        ['Construtora abre mais de 50 vagas de emprego em Itapetininga', 'Construtora oferece mais de 50 vagas de emprego para obras do Hospital Regional em Itapetininga'],
        ['Rio Itapetininga recua, mas três áreas seguem alagadas', 'Rio Itapetininga recua e Prefeitura inicia limpeza de áreas alagadas'],
        ['Chuva passa de 110 mm em Itapetininga e deixa desabrigados', 'Chuva de 110 mm deixa desabrigados em Itapetininga'],
    ]);

    it('não junta notícias diferentes', function (string $a, string $b) {
        expect((new Clusterer)->similar($a, $b))->toBeFalse();
    })->with([
        ['Defesa Civil de SP coloca estado em alerta laranja para temporais', 'Região entra em alerta para temporais neste domingo'],
        ['PAT de Itapetininga tem 338 vagas de emprego na próxima segunda', 'Construtora abre mais de 50 vagas de emprego em Itapetininga'],
        ['Equipe de natação de Itapetininga participa de torneio em Bauru', 'Tênis de Mesa de Itapetininga é vice-campeão no Jomi 2026'],
        ['Nota de falecimento: Maria da Silva', 'Nota de falecimento: João Souza'],
        ['Nota de falecimento: Maria Aparecida da Silva', 'Nota de falecimento: Maria José Santos'],
    ]);

    it('atribui o grupo da notícia parecida de outra fonte', function () {
        $first = item('jornal', 'Rio Itapetininga recua e Prefeitura inicia limpeza de áreas alagadas');
        $first->cluster = $first->id();
        $second = item('correio', 'Rio Itapetininga recua, mas três áreas seguem alagadas', date: '2026-10-25 14:00');

        (new Clusterer)->assign($second, [$first], 48);

        expect($second->cluster)->toBe($first->id());
    });

    it('não agrupa itens da mesma fonte nem fora da janela de tempo', function () {
        $first = item('jornal', 'Rio Itapetininga recua e Prefeitura inicia limpeza de áreas alagadas', date: '2026-10-01 10:00');
        $sameSource = item('jornal', 'Rio Itapetininga recua, mas três áreas seguem alagadas');
        $later = item('correio', 'Rio Itapetininga recua, mas três áreas seguem alagadas');

        (new Clusterer)->assign($sameSource, [$first], 48);
        (new Clusterer)->assign($later, [$first], 48);

        expect($sameSource->cluster)->toBe($sameSource->id())
            ->and($later->cluster)->toBe($later->id());
    });
});

describe('Olá Itapetininga', function () {
    it('está ativa e filtrada por "Itapetininga"', function () {
        expect(source('ola')->enabled)->toBeTrue()
            ->and(source('ola')->filter)->toBeTrue();
    });

    it('mantém só as notícias da cidade', function () {
        $items = (new RssAdapter)->parse(fixtureContents('ola.xml'), source('ola'));
        $titles = array_map(fn (Item $item) => $item->title, array_filter($items, fn (Item $item) => (new LocalFilter)->accepts($item, source('ola'))));

        expect($titles)->toContain('Mouhamed Harfouch traz comédia autobiográfica a Itapetininga')
            ->not->toContain('Marisa Monte volta a São Paulo para despedida da turnê ‘Phonica’');
    });
});
