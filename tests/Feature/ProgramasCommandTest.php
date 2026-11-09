<?php

use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * @param  Closure|null  $gemini  resposta do Gemini (padrão: notícia válida)
 */
function fakeYoutube(?Closure $gemini = null): void
{
    Http::fake([
        'www.youtube.com/@tvitape2/streams' => fn () => Http::response(fixtureContents('youtube-streams.html')),
        'generativelanguage.googleapis.com/*' => $gemini ?? geminiResponse(videoArticle()),
    ]);
}

function episode(string $video): ?array
{
    return JsonFile::read(config('itape.paths.data').'/programas/telescopio/'.$video.'.json');
}

beforeEach(function () {
    // Na janela de 7 dias (desde 02/11) estão os episódios de 03/11 (FTQML3B-wI4) e 04/11 (Lf_ZIAQo0yM).
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08 18:00', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => 'chave-de-teste', 'itape.programas.per_run' => 2]);
    // Só o Telescópio e só a aba "Ao vivo" (fixture), sem a data de início do recurso.
    config(['programas' => ['telescopio' => [...config('programas.telescopio'), 'pages' => ['https://www.youtube.com/@tvitape2/streams']]]]);
    config(['itape.programas.since' => '2026-10-01']);
});

it('guarda os episódios recentes e ignora cortes, jogos e lives agendadas', function () {
    config(['itape.camara.gemini.key' => null]);
    fakeYoutube();

    $this->artisan('programas')
        ->expectsOutputToContain('7 episódios no canal · 2 novos')
        ->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM'))->toMatchArray([
        'title' => 'Telescópio - 04/11/2026 - Geopolítica Internacional',
        'topic' => 'Geopolítica Internacional',
        'date' => '2026-11-04',
        'seconds' => 6033,
    ])
        ->and(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article')
        ->and(episode('FTQML3B-wI4'))->not->toBeNull()
        // 30/10: fora da janela de 7 dias.
        ->and(episode('PXWCTnG_hvk'))->toBeNull()
        ->and(geminiCalls())->toBe(0);
});

it('escreve a notícia com o Gemini a partir do link do vídeo', function () {
    fakeYoutube();

    $this->artisan('programas')->assertSuccessful();
    $episode = episode('FTQML3B-wI4');
    $request = Http::recorded(fn ($r) => str_contains($r->url(), 'generativelanguage') && str_contains($r->body(), 'FTQML3B-wI4'))->first()[0];

    expect($episode['article']['titulo'])->toBe('Protetores de animais pedem castração gratuita em Itapetininga')
        ->and($episode['written_by'])->toBe('gemini:gemini-3.5-flash-lite')
        ->and($episode['article']['momentos'][3]['segundos'])->toBe(3900)
        ->and($request['input'][0])->toBe(['type' => 'video', 'uri' => 'https://www.youtube.com/watch?v=FTQML3B-wI4'])
        ->and($request['input'][1]['text'])->toContain('terça-feira, 3 de novembro de 2026')->toContain('1:30:36')
        ->and(episode('Lf_ZIAQo0yM'))->toHaveKey('article');
});

it('não publica nada quando o Gemini falha e tenta de novo depois', function () {
    fakeYoutube(fn () => Http::response(['error' => ['message' => 'high demand']], 503));

    $this->artisan('programas')
        ->expectsOutputToContain('sem notícia (tenta de novo depois)')
        ->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article')
        ->and(episode('Lf_ZIAQo0yM')['attempts'])->toBe(1)
        ->and(episode('Lf_ZIAQo0yM')['error'])->toContain('gemini-3.5-flash-lite: HTTP 503')->toContain('gemini-3.8-flash: HTTP 503')
        // Os dois modelos, nos dois episódios.
        ->and(geminiCalls())->toBe(4);

    $this->artisan('programas')->assertSuccessful();
    expect(episode('Lf_ZIAQo0yM')['attempts'])->toBe(2);
});

it('desiste do episódio depois do máximo de tentativas', function () {
    config(['itape.programas.max_attempts' => 2]);
    fakeYoutube(fn () => Http::response(['error' => ['message' => 'falhou']], 500));

    $this->artisan('programas')->assertSuccessful();
    $this->artisan('programas')->assertSuccessful();
    $this->artisan('programas')->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM')['attempts'])->toBe(2)
        ->and(geminiCalls())->toBe(8);
});

it('recusa o texto que não passa na conferência', function () {
    fakeYoutube(geminiResponse(videoArticle(['fechamento' => 'Doações pelo (15) 99649-8734.'])));

    $this->artisan('programas')->assertSuccessful();

    expect(episode('FTQML3B-wI4'))->not->toHaveKey('article')
        ->and(episode('FTQML3B-wI4')['error'])->toContain('Dado de contato');
});

it('escreve no máximo per_run episódios por execução', function () {
    config(['itape.programas.per_run' => 1]);
    fakeYoutube();

    $this->artisan('programas')->assertSuccessful();

    // O mais novo primeiro.
    expect(episode('Lf_ZIAQo0yM'))->toHaveKey('article')
        ->and(episode('FTQML3B-wI4'))->not->toHaveKey('article');
});

it('segue em frente se o YouTube estiver fora do ar', function () {
    Http::fake(['www.youtube.com/*' => Http::response('', 500)]);

    $this->artisan('programas')
        ->expectsOutputToContain('Telescópio: YouTube respondeu HTTP 500')
        ->assertSuccessful();
});

it('manda menos quadros por segundo quando o programa pede', function () {
    config(['programas.telescopio.fps' => 0.2, 'itape.programas.per_run' => 1]);
    fakeYoutube();

    $this->artisan('programas')->assertSuccessful();
    $request = Http::recorded(fn ($r) => str_contains($r->url(), 'generativelanguage'))->first()[0];

    expect($request['input'][0]['processing'])->toBe(['type' => 'static', 'fps' => 0.2]);
});

it('não gasta tentativa quando a cota do Gemini acaba e para a execução', function () {
    fakeYoutube(fn () => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429));

    $this->artisan('programas')->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article')
        ->and(episode('Lf_ZIAQo0yM')['attempts'])->toBe(0)
        ->and(episode('Lf_ZIAQo0yM')['error'])->toContain('HTTP 429')
        // Parou no primeiro episódio: os dois modelos, uma vez.
        ->and(geminiCalls())->toBe(2)
        ->and(episode('FTQML3B-wI4'))->not->toHaveKey('error');
});

it('escolhe o episódio mais novo entre todos os programas', function () {
    config(['itape.programas.per_run' => 1, 'programas.outro' => [...config('programas.telescopio'), 'name' => 'Outro', 'pages' => ['https://www.youtube.com/@outro/streams']]]);
    Http::fake([
        'www.youtube.com/@tvitape2/streams' => fn () => Http::response(fixtureContents('youtube-streams.html')),
        'www.youtube.com/@outro/streams' => fn () => Http::response('<script>var ytInitialData = {"contents":[]};</script>'),
        'generativelanguage.googleapis.com/*' => geminiResponse(videoArticle()),
    ]);
    JsonFile::write(config('itape.paths.data').'/programas/outro/XYZ.json', [
        'program' => 'outro', 'video' => 'XYZ', 'title' => 'Telescópio - 05/11/2026 - X', 'topic' => 'X', 'date' => '2026-11-05', 'seconds' => 5436,
        'found_at' => '2026-11-08T17:00:00-03:00',
    ]);

    $this->artisan('programas')->expectsOutputToContain('Notícia do Outro (XYZ)')->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article');
});

it('lê também a aba "Vídeos" e não pega episódios antes da data de início', function () {
    config(['itape.camara.gemini.key' => null, 'itape.programas.since' => '2026-11-04']);
    config(['programas.telescopio.pages' => ['https://www.youtube.com/@tvitape2/streams', 'https://www.youtube.com/@tvitape2/videos']]);
    Http::fake([
        'www.youtube.com/@tvitape2/streams' => fn () => Http::response(fixtureContents('youtube-streams.html')),
        'www.youtube.com/@tvitape2/videos' => fn () => Http::response(fixtureContents('youtube-videos.html')),
    ]);

    $this->artisan('programas')->expectsOutputToContain('18 episódios no canal · 3 novos')->assertSuccessful();

    // 04/11 (live), 05/11 e 06/11 (vídeo comum); 03/11 é antes da data de início.
    expect(episode('Lf_ZIAQo0yM'))->not->toBeNull()
        ->and(episode('It9L7IWXk0E')['topic'])->toBe('256 anos de Itapetininga')
        ->and(episode('Wsw9YeliCdY'))->not->toBeNull()
        ->and(episode('FTQML3B-wI4'))->toBeNull();
});

it('segue com a outra página se uma das abas falhar', function () {
    config(['itape.camara.gemini.key' => null]);
    config(['programas.telescopio.pages' => ['https://www.youtube.com/@tvitape2/streams', 'https://www.youtube.com/@tvitape2/videos']]);
    Http::fake([
        'www.youtube.com/@tvitape2/streams' => fn () => Http::response(fixtureContents('youtube-streams.html')),
        'www.youtube.com/@tvitape2/videos' => Http::response('', 503),
    ]);

    $this->artisan('programas')
        ->expectsOutputToContain('2 novos')
        ->expectsOutputToContain('YouTube respondeu HTTP 503 (https://www.youtube.com/@tvitape2/videos)')
        ->assertSuccessful();
});

it('espera o vídeo ficar disponível sem gastar tentativa nem a vez dos outros (403)', function () {
    config(['itape.programas.per_run' => 1]);
    // A gravação de 04/11 ainda está sendo processada; a de 03/11 já pode ser lida.
    fakeYoutube(fn ($request) => str_contains($request->body(), 'Lf_ZIAQo0yM')
        ? Http::response(['error' => ['message' => 'The caller does not have permission', 'code' => 'permission_denied']], 403)
        : geminiResponse(videoArticle())());

    $this->artisan('programas')->expectsOutputToContain('vídeo ainda não disponível')->assertSuccessful();

    expect(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article')
        ->and(episode('Lf_ZIAQo0yM')['attempts'])->toBe(0)
        ->and(episode('Lf_ZIAQo0yM')['error'])->toContain('HTTP 403')
        ->and(episode('FTQML3B-wI4'))->toHaveKey('article');
});

it('desiste do episódio que continua indisponível três dias depois de encontrado', function () {
    config(['itape.camara.gemini.key' => null]);
    fakeYoutube();
    $this->artisan('programas')->assertSuccessful();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-11 19:00', 'America/Sao_Paulo'));
    config(['itape.camara.gemini.key' => 'chave-de-teste']);
    fakeYoutube();
    $this->artisan('programas')->assertSuccessful();

    expect(geminiCalls())->toBe(0)
        ->and(episode('Lf_ZIAQo0yM'))->not->toHaveKey('article');
});
