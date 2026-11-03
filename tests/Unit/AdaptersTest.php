<?php

use App\Data\Item;
use App\Pipeline\LocalFilter;
use App\Sources\HtmlListAdapter;
use App\Sources\ParseException;
use App\Sources\RssAdapter;
use App\Sources\SitemapStreamAdapter;

describe('RSS', function () {
    it('lê o feed do g1 com imagem e data', function () {
        $items = (new RssAdapter)->parse(fixtureContents('g1.xml'), source('g1'));

        expect($items)->toHaveCount(100)
            ->and($items[0]->title)->toBe('Ponte de madeira cede e caminhão carregado com toras tomba e cai na água em Ribeirão Branco')
            ->and($items[0]->image)->toStartWith('https://s2-g1.glbimg.com/')
            ->and($items[0]->publishedAt->format('Y-m-d H:i'))->toBe('2026-10-25 16:48');
    });

    it('remove a legenda da foto e a chamada de WhatsApp do resumo do g1', function () {
        $items = (new RssAdapter)->parse(fixtureContents('g1.xml'), source('g1'));

        expect($items[0]->summary)->toStartWith('Um caminhão carregado com toras tombou')
            ->and(implode(' ', array_map(fn (Item $item) => $item->summary, $items)))
            ->not->toContain('Participe do canal');
    });

    it('converte o feed ISO-8859-1 do Jornal sem quebrar acentos', function () {
        $items = (new RssAdapter)->parse(fixtureContents('jornal.xml'), source('jornal'));

        expect($items)->toHaveCount(50)
            ->and($items[0]->summary)->toContain('Defesa Civil de São Paulo prevê')
            ->and($items[0]->summary)->not->toContain('�');
    });

    it('remove o rodapé padrão do WordPress', function () {
        $items = (new RssAdapter)->parse(fixtureContents('tvsmais.xml'), source('tvsmais'));

        expect($items)->toHaveCount(10)
            ->and($items[0]->summary)->not->toContain('O post');
    });

    it('lê os feeds da Câmara e do Xeh', function (string $key, int $count) {
        expect((new RssAdapter)->parse(fixtureContents("{$key}.xml"), source($key)))->toHaveCount($count);
    })->with([['camara', 1], ['xeh', 10]]);

    it('falha com conteúdo que não é feed', function () {
        (new RssAdapter)->parse('<html><body>Manutenção</body></html>', source('g1'));
    })->throws(ParseException::class);
});

describe('Lista HTML', function () {
    it('extrai os links de notícia da home da Prefeitura sem repetir', function () {
        $items = (new HtmlListAdapter)->parse(fixtureContents('prefeitura-home.html'), source('prefeitura'));
        $urls = array_map(fn (Item $item) => $item->url, $items);

        expect($items)->toHaveCount(8)
            ->and($urls)->each->toMatch('#^https://www\.itapetininga\.sp\.gov\.br/noticia/\d+/#')
            ->and(array_unique(array_map(fn (Item $item) => $item->id(), $items)))->toHaveCount(8);
    });

    it('resolve os links relativos da Folha', function () {
        $items = (new HtmlListAdapter)->parse(fixtureContents('folha-home.html'), source('folha'));

        expect($items)->toHaveCount(3)
            ->and($items[0]->url)->toBe('https://www.folhadeitapetininga.com.br/noticias/default.asp?id=1889')
            ->and($items[0]->title)->toBe('');
    });

    it('devolve lista vazia quando o layout muda e não há links', function () {
        expect((new HtmlListAdapter)->parse('<html><a href="/contato">Contato</a></html>', source('prefeitura')))->toBe([]);
    });
});

describe('Sitemap parcial', function () {
    it('pega só as notícias, respeitando o limite', function () {
        $items = (new SitemapStreamAdapter)->parse(fixtureContents('correio-sitemap-head.xml'), source('correio'));

        expect($items)->toHaveCount(25)
            ->and($items[0]->url)->toContain('/noticia/80788/')
            ->and($items[0]->fallbackTitle)->toStartWith('Feira livre da avenida')
            ->and($items[0]->publishedAt->format('Y-m-d H:i'))->toBe('2026-10-23 15:24');
    });

    it('aceita um sitemap cortado no meio de uma URL', function () {
        $body = substr(fixtureContents('correio-sitemap-head.xml'), 0, 5000).'<url><loc>https://correiodeitapetininga.com.br/noticia/1/cor';

        expect(fn () => (new SitemapStreamAdapter)->parse($body, source('correio')))->not->toThrow(Exception::class);
    });
});

describe('Jornal Panorâmico', function () {
    it('lê o feed e limpa o rodapé em inglês e o "[…]" do WordPress', function () {
        $items = (new RssAdapter)->parse(fixtureContents('panoramico.xml'), source('panoramico'));
        $summaries = implode(' ', array_map(fn (Item $item) => $item->summary.' '.$item->content, $items));

        expect($items)->toHaveCount(10)
            ->and($summaries)->not->toContain('first appeared on')
            ->and($summaries)->not->toContain('[…]')
            ->and($summaries)->not->toContain('[&#8230;]');
    });

    it('mantém só as notícias de Itapetininga', function () {
        $items = (new RssAdapter)->parse(fixtureContents('panoramico.xml'), source('panoramico'));
        $titles = array_map(fn (Item $item) => $item->title, array_filter($items, fn (Item $item) => (new LocalFilter)->accepts($item, source('panoramico'))));

        expect($titles)->toContain('PAT de Itapetininga oferece 266 vagas de emprego nesta terça-feira')
            ->not->toContain('ICMS dos combustíveis terá novos valores em todo o país a partir de fevereiro de 2027');
    });
});
