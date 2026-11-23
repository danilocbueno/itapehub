<?php

use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Resumo do dia válido para as notícias enviadas (ids n1, n2…).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dayArticle(array $overrides = []): array
{
    return [
        'titulo' => 'Chuva passa de 110 mm e deixa desabrigados em Itapetininga',
        'linha_fina' => 'Defesa Civil acompanha famílias atingidas; outras notícias do dia.',
        'lide' => 'A chuva deste sábado (24) passou de 110 mm em Itapetininga, segundo as notícias do dia.',
        'blocos' => [
            ['intertitulo' => 'Clima', 'paragrafos' => ['Famílias ficaram desabrigadas após a chuva.'], 'noticias' => ['n1']],
            ['intertitulo' => 'Cidade', 'paragrafos' => ['Outras notícias da cidade.'], 'noticias' => ['n2']],
        ],
        ...$overrides,
    ];
}

/**
 * Depois da coleta, só o Gemini responde (os fakes das fontes têm um "pega tudo" que viria antes).
 */
function fakeGemini(Closure $response): void
{
    Http::swap(new Factory);
    Http::fake(['generativelanguage.googleapis.com/*' => $response]);
}

function summaryOf(string $day): ?array
{
    return JsonFile::read(config('itape.paths.data').'/resumos/'.$day.'.json');
}

beforeEach(function () {
    fakeSources();
    $this->artisan('collect')->assertSuccessful();
    // Os dias das fixtures (a coleta é em 25/10/2026, às 17h).
    config(['itape.resumo.since' => '2026-10-20']);
});

it('sem a chave, publica a lista simples do dia anterior no site e no RSS', function () {
    $this->artisan('resumo')
        ->expectsOutputToContain('Resumo de 24/10 publicado como lista de títulos')
        ->assertSuccessful();

    $summary = summaryOf('2026-10-24');
    expect($summary['kind'])->toBe('lista')
        ->and($summary['news'][0])->toHaveKeys(['id', 'title', 'url', 'sources', 'time'])
        ->and($summary['news'][0]['id'])->toBe('n1');

    $this->artisan('build')->assertSuccessful();
    $feed = simplexml_load_string(built('feed.xml'));
    $page = built('resumo/2026/10/24/index.html');

    expect((string) $feed->channel->item[0]->link)->toBe('http://localhost:8000/resumo/2026/10/24/')
        ->and((string) $feed->channel->item[0]->title)->toBe('Resumo de sábado, 24/10: as notícias do dia em Itapetininga')
        // O texto inteiro vai no item, com os links das notícias.
        ->and((string) $feed->channel->item[0]->description)->toContain('<ul class="summary-list">')->toContain($summary['news'][0]['url'])
        ->and($page)->toContain('Resumo do dia · sábado, 24 de outubro de 2026')
        ->toContain('class="summary-list"')
        ->toContain('/arquivo/2026/10/24/">Todas as notícias de 24/10')
        // Na linha do tempo daquele dia, como notícia do próprio site.
        ->and(built('arquivo/2026/10/24/index.html'))->toContain('href="http://localhost:8000/resumo/2026/10/24/"')
        ->and(built('busca.json'))->toContain('http://localhost:8000/resumo/2026/10/24/');
});

it('não resume o dia antes da coleta da madrugada seguinte', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 02:00', 'America/Sao_Paulo'));

    $this->artisan('resumo')->assertSuccessful();

    expect(summaryOf('2026-10-24'))->toBeNull();
});

it('escreve o resumo com o Gemini, com as notícias de cada bloco, e não o reescreve depois', function () {
    config(['itape.camara.gemini.key' => 'chave-de-teste']);
    fakeGemini(geminiResponse(dayArticle()));

    $this->artisan('resumo')
        ->expectsOutputToContain('Resumo de 24/10 escrito por gemini:gemini-3.5-flash-lite')
        ->assertSuccessful();

    $summary = summaryOf('2026-10-24');
    $request = Http::recorded(fn ($r) => str_contains($r->url(), 'generativelanguage'))->first()[0]->data();

    expect($summary['kind'])->toBe('ia')
        ->and($summary['article']['blocos'][1]['noticias'])->toBe(['n2'])
        ->and($request['input'])->toContain('sábado, 24 de outubro de 2026')->toContain('"id":"n1"')
        // Uma chamada por execução: os dias anteriores já passaram do prazo e saem como lista.
        ->and(geminiCalls())->toBe(1)
        ->and(summaryOf('2026-10-23')['kind'] ?? 'vazio')->toBeIn(['lista', 'vazio']);

    $this->artisan('build')->assertSuccessful();
    $page = built('resumo/2026/10/24/index.html');

    expect($page)->toContain('<h1 class="page-title">Chuva passa de 110 mm e deixa desabrigados em Itapetininga</h1>')
        ->toContain('gerado automaticamente com inteligência artificial')
        ->toContain('<h2>Cidade</h2>')
        ->toContain('class="summary-sources"')
        ->toContain($summary['news'][1]['url']);

    $this->artisan('resumo')->expectsOutputToContain('nada pendente')->assertSuccessful();
    expect(geminiCalls())->toBe(1);
});

it('não publica o texto que cita notícia inexistente e tenta de novo depois', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 05:00', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => 'chave-de-teste']);
    fakeGemini(geminiResponse(dayArticle(['blocos' => [
        ['intertitulo' => 'Clima', 'paragrafos' => ['Texto.'], 'noticias' => ['n999']],
    ]])));

    $this->artisan('resumo')
        ->expectsOutputToContain('Resumo de 24/10 sem texto (tenta de novo depois)')
        ->assertSuccessful();

    expect(summaryOf('2026-10-24'))->not->toHaveKey('kind')
        ->and(summaryOf('2026-10-24')['attempts'])->toBe(1)
        ->and(summaryOf('2026-10-24')['error'])->toContain('n999');
});

it('publica a lista simples quando o Gemini não consegue até o meio-dia', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 12:30', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => 'chave-de-teste']);
    fakeGemini(fn () => Http::response(['error' => ['message' => 'falhou']], 500));

    $this->artisan('resumo')->assertSuccessful();

    expect(summaryOf('2026-10-24')['kind'])->toBe('lista')
        ->and(summaryOf('2026-10-24')['error'])->toContain('HTTP 500');
});

it('não gasta tentativa quando o Gemini recusa por cota ou sobrecarga', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 05:00', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => 'chave-de-teste']);
    fakeGemini(fn () => Http::response(['error' => ['message' => 'high demand']], 503));

    $this->artisan('resumo')->assertSuccessful();

    expect(summaryOf('2026-10-24')['attempts'])->toBe(0)
        ->and(summaryOf('2026-10-24')['error'])->toContain('HTTP 503');
});

it('deixa o resumo fora da agenda, dos temas e do resumo de 24 horas', function () {
    $this->artisan('resumo')->assertSuccessful();
    $this->artisan('build')->assertSuccessful();

    foreach (['agenda/index.html', 'hoje/index.html', 'tema/clima/index.html'] as $path) {
        expect(built($path))->not->toContain('/resumo/2026/10/24/');
    }
});
