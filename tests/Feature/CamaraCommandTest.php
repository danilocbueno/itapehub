<?php

use App\Camara\SessionRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

function fakeSiscam(): void
{
    Http::fake([
        'itapetininga.siscam.com.br/sessoes' => fn () => Http::response(fixtureContents('siscam-sessoes.html')),
        'itapetininga.siscam.com.br/Sessoes/Sessao/*' => fn () => Http::response(fixtureContents('siscam-sessao.html')),
        'itapetininga.siscam.com.br/Sessoes/Documento/*' => fn () => Http::response(fixtureContents('siscam-roteiro.html')),
    ]);
}

function camaraSessions(): SessionRepository
{
    return new SessionRepository(config('itape.paths.data').'/camara/sessoes');
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 08:00', 'America/Sao_Paulo'));
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
