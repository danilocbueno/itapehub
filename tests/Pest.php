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
        'www.grupocine.com.br/*' => 'grupocine-programacao.html',
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

/**
 * Resposta da API Interactions com o texto JSON da notícia.
 *
 * @param  array<string, mixed>  $article
 */
function geminiResponse(array $article): Closure
{
    return fn () => Http::response([
        'id' => 'v1_teste',
        'status' => 'completed',
        'steps' => [
            ['type' => 'thought', 'signature' => 'x'],
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode($article, JSON_UNESCAPED_UNICODE)]]],
        ],
    ]);
}

function geminiCalls(): int
{
    return Http::recorded(fn ($request) => str_contains($request->url(), 'generativelanguage'))->count();
}

/**
 * Notícia válida de um episódio de 1h30 (5400 s) de terça-feira, 3/11/2026.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function videoArticle(array $changes = []): array
{
    return [
        'titulo' => 'Protetores de animais pedem castração gratuita em Itapetininga',
        'linha_fina' => 'No Telescópio, convidados falaram sobre abandono e adoção responsável.',
        'lide' => 'Em entrevista ao programa Telescópio, da TV Itapê, na terça-feira (3), protetores de animais pediram mais vagas de castração gratuita.',
        'blocos' => [
            ['intertitulo' => 'Castração', 'paragrafos' => ['Segundo a protetora, a fila de castração tem mais de 200 animais.']],
            ['intertitulo' => '', 'paragrafos' => ['O veterinário convidado explicou como funciona a adoção.']],
        ],
        'fechamento' => '',
        'participantes' => [['nome' => '', 'papel' => 'apresentador'], ['nome' => 'Ana Souza', 'papel' => 'protetora de animais']],
        'momentos' => [
            ['inicio' => '01:30', 'assunto' => 'Abertura'],
            ['inicio' => '15:00', 'assunto' => 'Castração'],
            ['inicio' => '40:00', 'assunto' => 'Abandono'],
            ['inicio' => '1:05:00', 'assunto' => 'Adoção'],
        ],
        'omitidos' => [],
        ...$changes,
    ];
}
