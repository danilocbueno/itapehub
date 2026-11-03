<?php

use App\Support\Dates;
use App\Support\Text;
use App\Support\Url;

describe('Text', function () {
    it('converte HTML em texto de uma linha', function () {
        expect(Text::clean('<p>Chuva &amp; vento</p><p>em&nbsp;<b>Itapetininga</b></p>'))
            ->toBe('Chuva & vento em Itapetininga');
    });

    it('decodifica entidades duplamente codificadas', function () {
        expect(Text::clean('Prefeitura &amp;quot;oficial&amp;quot;'))->toBe('Prefeitura "oficial"');
    });

    it('remove tags que vinham codificadas como texto', function () {
        expect(Text::clean('Resumo da notícia. &lt;p&gt;Rodapé&lt;/p&gt;'))->toBe('Resumo da notícia. Rodapé');
    });

    it('converte texto ISO-8859-1 para UTF-8', function () {
        expect(Text::clean(mb_convert_encoding('Notícias da região', 'ISO-8859-1', 'UTF-8')))
            ->toBe('Notícias da região');
    });

    it('corta resumos em fim de palavra', function () {
        $excerpt = Text::excerpt('Defesa Civil emite alerta para chuva forte em Itapetininga neste domingo', 40);

        expect($excerpt)->toEndWith('…')
            ->and(mb_strlen($excerpt))->toBeLessThanOrEqual(41)
            ->and($excerpt)->not->toContain('neste');
    });

    it('não mexe em textos curtos', function () {
        expect(Text::excerpt('Curto', 40))->toBe('Curto');
    });
});

describe('Url', function () {
    it('remove parâmetros de rastreamento e o fragmento', function () {
        expect(Url::clean('https://site.com.br/noticia?id=3&utm_source=x&fbclid=y#topo'))
            ->toBe('https://site.com.br/noticia?id=3');
    });

    it('identifica a mesma notícia com ou sem www, https e barra final', function () {
        expect(Url::id('https://www.site.com.br/noticia/10/'))
            ->toBe(Url::id('http://site.com.br/noticia/10'))
            ->and(Url::id('https://site.com.br/noticia/10'))->not->toBe(Url::id('https://site.com.br/noticia/11'));
    });

    it('resolve links relativos', function () {
        expect(Url::absolute('noticias/default.asp?id=1', 'https://www.folhadeitapetininga.com.br/'))
            ->toBe('https://www.folhadeitapetininga.com.br/noticias/default.asp?id=1');
    });
});

describe('Dates', function () {
    it('lê RFC 2822 e converte para o horário de Brasília', function () {
        expect(Dates::parse('Fri, 23 Oct 2026 23:37:00 GMT')->format('Y-m-d H:i P'))
            ->toBe('2026-10-23 20:37 -03:00');
    });

    it('trata datas sem fuso como horário de Brasília', function () {
        expect(Dates::parse('2026-10-23T15:24:04.347841')->format('H:i P'))->toBe('15:24 -03:00');
    });

    it('ignora o "Z" de sites que marcam horário local como UTC', function () {
        expect(Dates::parse('2026-10-23T15:24:04.347Z', naive: true)->format('H:i'))->toBe('15:24');
    });

    it('encontra datas por extenso em português', function () {
        $parsed = Dates::fromText('Esporte, Lazer e Juventude - Sexta-feira, 23 de Outubro de 2026');

        expect($parsed->date->format('Y-m-d'))->toBe('2026-10-23')
            ->and($parsed->hasTime)->toBeFalse();
    });

    it('encontra datas numéricas com hora', function () {
        $parsed = Dates::fromText('Publicado em 24/10/2026 às 14h30');

        expect($parsed->date->format('Y-m-d H:i'))->toBe('2026-10-24 14:30')
            ->and($parsed->hasTime)->toBeTrue();
    });

    it('devolve null quando não há data', function () {
        expect(Dates::parse(''))->toBeNull()
            ->and(Dates::fromText('sem data aqui'))->toBeNull();
    });
});
