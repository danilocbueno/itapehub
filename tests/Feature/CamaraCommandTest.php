<?php

use App\Camara\SessionRepository;
use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @param  array<string, mixed>  $extra  respostas além do SisCam (ex.: Gemini)
 */
function fakeSiscam(array $extra = []): void
{
    Http::fake([
        ...$extra,
        'itapetininga.siscam.com.br/sessoes' => fn () => Http::response(fixtureContents('siscam-sessoes.html')),
        'itapetininga.siscam.com.br/Sessoes/Sessao/*' => fn () => Http::response(fixtureContents('siscam-sessao.html')),
        'itapetininga.siscam.com.br/Sessoes/Documento/*' => fn () => Http::response(fixtureContents('siscam-roteiro.html')),
    ]);
}

function camaraSessions(): SessionRepository
{
    return new SessionRepository(config('itape.paths.data').'/camara/sessoes');
}

function camaraNews(int $id): ?array
{
    return JsonFile::read(config('itape.paths.data').'/camara/noticias/'.$id.'.json');
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

function geminiArticle(string $councilor = 'o vereador Miguel Arcanjo'): array
{
    return [
        'titulo' => 'Câmara aprova urgência para regras de feiras e cobra estradas rurais',
        'linha_fina' => 'Vereadores também pediram reparos na iluminação da Marginal do Chá.',
        'lide' => 'A Câmara de Itapetininga aprovou na sessão de quinta-feira (29) a urgência de dois projetos da Prefeitura.',
        'blocos' => [
            ['intertitulo' => 'Estradas rurais', 'paragrafos' => ["Na zona rural, {$councilor} pediu manutenção das estradas do Viracopos."]],
            ['intertitulo' => '', 'paragrafos' => ['A vereadora Marina Nalesso pediu informações ao Hospital Léo Orsi.']],
        ],
        'fechamento' => '18 dos 19 vereadores estiveram presentes.',
        'omitidos' => [['documento' => 'Correspondências Recebidas Nº 340/2026', 'motivo' => 'Denúncia anônima.']],
    ];
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 08:00', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => null]);
    // Nenhum teste espera de verdade entre as tentativas.
    Sleep::fake();
});

it('guarda as sessões das últimas duas semanas com presença, votações e roteiro', function () {
    fakeSiscam();

    $this->artisan('camara')->assertSuccessful();
    $session = camaraSessions()->find(1608);

    expect($session)->not->toBeNull()
        ->and($session->votes)->toHaveCount(13)
        ->and($session->agenda)->toHaveCount(7)
        // Janela de 14 dias a partir de 30/10: sessões de 19/10 a 29/10; a de 15/10 fica de fora.
        ->and(array_map(fn ($s) => $s->id, camaraSessions()->all()))->toBe([1608, 1607, 1606, 1605]);
});

it('não busca de novo uma sessão já encerrada', function () {
    fakeSiscam();
    $this->artisan('camara')->assertSuccessful();

    fakeSiscam();
    $this->artisan('camara')->assertSuccessful();

    Http::assertSentCount(1);
});

it('não falha a publicação quando o SisCam está fora do ar', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $this->artisan('camara')
        ->expectsOutputToContain('SisCam indisponível')
        ->assertSuccessful();
});

it('escreve a notícia de cada sessão encerrada pelo modelo fixo quando não há chave do Gemini', function () {
    fakeSiscam();

    $this->artisan('camara')
        ->expectsOutputToContain('sem GEMINI_API_KEY')
        ->assertSuccessful();

    $news = camaraNews(1608);

    expect($news['written_by'])->toBe('modelo')
        ->and($news['session'])->toBe(1608)
        ->and($news['article']['titulo'])->toBe('Vereadores fazem 21 pedidos na sessão de 29 de outubro');
});

it('escreve a notícia pelo Gemini com as regras do prompt e o esquema da resposta', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => geminiResponse(geminiArticle())]);

    $this->artisan('camara')->assertSuccessful();
    $news = camaraNews(1608);

    expect($news['written_by'])->toBe('gemini:gemini-3.8-flash')
        ->and($news['closed_at'])->toBe('20:13')
        ->and($news['format'])->toBe('texto')
        ->and($news['article']['titulo'])->toBe('Câmara aprova urgência para regras de feiras e cobra estradas rurais')
        ->and($news['article']['blocos'][0]['intertitulo'])->toBe('Estradas rurais')
        ->and($news['article']['omitidos'][0]['documento'])->toBe('Correspondências Recebidas Nº 340/2026');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($request->url(), 'v1beta/interactions')
            && $request->hasHeader('x-goog-api-key', 'chave-teste')
            && $request->hasHeader('Api-Revision', '2026-05-20')
            && $body['model'] === 'gemini-3.8-flash'
            && $body['store'] === false
            && str_contains($body['system_instruction'], 'Respeite a fase de cada votação')
            && str_contains($body['system_instruction'], 'O que é proibido inventar')
            && $body['response_format']['schema']['required'] === ['titulo', 'linha_fina', 'lide', 'blocos', 'fechamento', 'omitidos']
            // A lista de vereadores vai com o cargo no gênero certo.
            && str_contains($body['input'], '{"nome":"Marina Nalesso","cargo":"vereadora"}')
            && str_contains($body['input'], '{"nome":"Miguel Arcanjo","cargo":"vereador"}')
            && $body['response_format']['mime_type'] === 'application/json'
            // Os nomes já vão curtos para o modelo.
            && str_contains($body['input'], 'Miguel Arcanjo')
            && ! str_contains($body['input'], 'Miguel Arcanjo Máximo de Jesus');
    });
});

it('usa o modelo fixo quando o Gemini chama de vereador quem não está na lista', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => geminiResponse(geminiArticle('o vereador Fulano Inventado'))]);

    $this->artisan('camara')
        ->expectsOutputToContain('Vereador desconhecido no texto: Fulano Inventado')
        ->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('modelo')
        ->and(camaraNews(1608)['format'])->toBe('lista');
});

it('usa o modelo fixo quando o Gemini erra o gênero do cargo', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => geminiResponse(geminiArticle('a vereadora Miguel Arcanjo'))]);

    $this->artisan('camara')
        ->expectsOutputToContain('Cargo no gênero errado no texto: vereadora Miguel Arcanjo')
        ->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('modelo');
});

it('reescreve em texto corrido uma notícia antiga do Gemini em lista', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    JsonFile::write(config('itape.paths.data').'/camara/noticias/1608.json', [
        'session' => 1608, 'title' => '53ª Sessão Ordinária de 2026', 'date' => '2026-10-29',
        'written_by' => 'gemini:gemini-3.8-flash', 'article' => ['titulo' => 'Antiga em lista'],
    ]);
    fakeSiscam(['generativelanguage.googleapis.com/*' => geminiResponse(geminiArticle())]);

    $this->artisan('camara')->assertSuccessful();

    expect(camaraNews(1608)['format'])->toBe('texto')
        ->and(camaraNews(1608)['article']['titulo'])->toBe('Câmara aprova urgência para regras de feiras e cobra estradas rurais');
});

it('usa o modelo fixo quando o Gemini está fora do ar ou sem cota', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429)]);

    $this->artisan('camara')
        ->expectsOutputToContain('gemini-3.8-flash: HTTP 429: Quota exceeded')
        ->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('modelo');
});

it('não escreve de novo a notícia de uma sessão que já tem uma', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => geminiResponse(geminiArticle())]);

    $this->artisan('camara')->assertSuccessful();
    $this->artisan('camara')->assertSuccessful();

    // Uma chamada por sessão encerrada (quatro na janela de teste), só na primeira execução.
    expect(geminiCalls())->toBe(4);
});

it('tenta de novo o mesmo modelo quando há novas tentativas configuradas', function () {
    config(['itape.camara.gemini.key' => 'chave-teste', 'itape.camara.gemini.retries' => 1]);
    fakeSiscam(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'high demand']], 503)
        ->pushResponse(geminiResponse(geminiArticle())())
        ->pushResponse(geminiResponse(geminiArticle())()),
    ]);

    $this->artisan('camara')->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('gemini:gemini-3.8-flash');
    Sleep::assertSleptTimes(1);
});

it('passa direto para o modelo reserva quando o principal está sobrecarregado', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    fakeSiscam(['generativelanguage.googleapis.com/*' => fn ($request) => $request['model'] === 'gemini-3.8-flash'
        ? Http::response(['error' => ['message' => 'high demand']], 503)
        : geminiResponse(geminiArticle())(),
    ]);

    $this->artisan('camara')->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('gemini:gemini-3.5-flash-lite');
    // Sem novas tentativas no mesmo modelo: não espera entre as chamadas.
    Sleep::assertNeverSlept();
});

it('troca a notícia do modelo fixo pela do Gemini quando ele volta a responder', function () {
    config(['itape.camara.gemini.key' => 'chave-teste']);
    $online = false;
    fakeSiscam(['generativelanguage.googleapis.com/*' => function () use (&$online) {
        return $online ? geminiResponse(geminiArticle())() : Http::response(['error' => ['message' => 'high demand']], 503);
    }]);

    $this->artisan('camara')->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('modelo')
        ->and(camaraNews(1608)['gemini_attempts'])->toBe(1);

    $online = true;
    $this->artisan('camara')->assertSuccessful();

    expect(camaraNews(1608)['written_by'])->toBe('gemini:gemini-3.8-flash')
        ->and(camaraNews(1608))->not->toHaveKey('gemini_attempts');
});

it('desiste do Gemini para a sessão depois do limite de tentativas', function () {
    config([
        'itape.camara.gemini.key' => 'chave-teste',
        'itape.camara.gemini.models' => ['gemini-3.8-flash'],
        'itape.camara.gemini.retries' => 0,
        'itape.camara.gemini.max_attempts' => 2,
    ]);
    fakeSiscam(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'high demand']], 503)]);

    foreach (range(1, 3) as $run) {
        $this->artisan('camara')->assertSuccessful();
    }

    // Quatro sessões encerradas na janela: duas tentativas cada; a terceira execução já não chama.
    expect(geminiCalls())->toBe(8)
        ->and(camaraNews(1608)['gemini_attempts'])->toBe(2)
        ->and(camaraNews(1608)['written_by'])->toBe('modelo');
});
