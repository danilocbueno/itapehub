<?php

use App\Resumo\DayStory;
use Carbon\CarbonImmutable;

function checkDay(array $data): array
{
    return DayStory::check($data, ['n1', 'n2'], CarbonImmutable::parse('2026-11-23', 'America/Sao_Paulo'));
}

function dayData(array $overrides = []): array
{
    return [
        'titulo' => 'Temporal deixa bairros sem luz em Itapetininga',
        'linha_fina' => 'Câmara aprova projeto; outras notícias do dia.',
        'lide' => 'O temporal desta segunda-feira (23) deixou bairros sem luz.',
        'blocos' => [['intertitulo' => 'Clima', 'paragrafos' => ['Texto.', ' '], 'noticias' => ['n1', 'n1', 'n2']]],
        ...$overrides,
    ];
}

it('aceita o resumo válido, sem parágrafos vazios nem notícias repetidas', function () {
    $article = checkDay(dayData());

    expect($article['blocos'][0]['paragrafos'])->toBe(['Texto.'])
        ->and($article['blocos'][0]['noticias'])->toBe(['n1', 'n2']);
});

it('recusa o resumo com problema', function (array $overrides, string $error) {
    expect(fn () => checkDay(dayData($overrides)))->toThrow(InvalidArgumentException::class, $error);
})->with([
    'notícia que não existe' => [['blocos' => [['intertitulo' => 'X', 'paragrafos' => ['Texto.'], 'noticias' => ['n3']]]], 'n3'],
    'bloco sem notícia' => [['blocos' => [['intertitulo' => 'X', 'paragrafos' => ['Texto.'], 'noticias' => []]]], 'sem notícia de origem'],
    'sem blocos' => [['blocos' => []], 'de um a seis blocos'],
    'dia da semana errado' => [['lide' => 'O temporal desta terça-feira (23) deixou bairros sem luz.'], 'Dia da semana errado'],
    'telefone' => [['lide' => 'Ligue (15) 99999-1234.'], 'Dado de contato'],
    'título longo' => [['titulo' => str_repeat('a', 121)], 'titulo'],
]);
