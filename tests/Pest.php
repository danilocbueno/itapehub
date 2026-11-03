<?php

use App\Data\Source;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Conteúdo de um arquivo em tests/Fixtures (cópias reais das fontes).
 */
function fixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/Fixtures/'.$name);
}

function source(string $key): Source
{
    return Source::all()[$key];
}

/**
 * Responde a todas as fontes com as cópias salvas em tests/Fixtures.
 *
 * @param  array<string, mixed>  $overrides  padrões de URL que devem responder diferente
 */
function fakeSources(array $overrides = []): void
{
    $fixtures = [
        'g1.globo.com/rss/*' => 'g1.xml',
        'www.jornaldeitapetininga.com.br/feed' => 'jornal.xml',
        'correiodeitapetininga.com.br/sitemap.xml' => 'correio-sitemap-head.xml',
        'correiodeitapetininga.com.br/noticia/*' => 'correio-article.html',
        'www.itapetininga.sp.gov.br/' => 'prefeitura-home.html',
        'www.itapetininga.sp.gov.br/noticia/*' => 'prefeitura-article.html',
        'www.folhadeitapetininga.com.br/' => 'folha-home.html',
        'www.folhadeitapetininga.com.br/noticias/*' => 'folha-article.html',
        'www.tvsmais.com.br/?s=*' => 'tvsmais.xml',
        'www.tvsmais.com.br/*' => 'tvsmais-article.html',
        'www.camaraitapetininga.sp.gov.br/*' => 'camara.xml',
        'xeh.com.br/feed/' => 'xeh.xml',
        'olaitapetininga.com.br/feed' => 'ola.xml',
        'jornalpanoramico.com.br/feed/' => 'panoramico.xml',
        'api.open-meteo.com/*' => 'open-meteo.json',
        'apiprevmet3.inmet.gov.br/*' => 'inmet.xml',
    ];

    // Closures criam uma resposta nova por requisição (o stream é consumido).
    $fakes = array_map(fn (string $file) => fn () => Http::response(fixtureContents($file)), $fixtures);
    $custom = array_map(fn ($response) => $response instanceof Closure ? $response : fn () => $response, $overrides);

    // Os padrões são testados em ordem: os específicos do teste vêm primeiro.
    $fakes = $custom + $fakes;

    // Páginas de notícia sem cópia salva.
    $fakes['*'] = fn () => Http::response('', 404);

    Http::fake($fakes);
}
