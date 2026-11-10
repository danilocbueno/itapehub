<?php

use App\Programas\ChannelParser;
use App\Programas\Program;
use App\Programas\VideoStory;
use Carbon\CarbonImmutable;

function telescopio(): Program
{
    return Program::all()['telescopio'];
}

function checkArticle(array $data): array
{
    return VideoStory::check($data, 5400, CarbonImmutable::parse('2026-11-03', 'America/Sao_Paulo'));
}

it('lê os vídeos da aba "Ao vivo" com a duração', function () {
    $videos = ChannelParser::videos(fixtureContents('youtube-streams.html'));
    $byId = array_column($videos, null, 'id');

    expect($videos)->toHaveCount(30)
        ->and($byId['i_BM13C0VFU']['title'])->toBe('Telescópio - 26/10/2026 - Análise Política (Jean Nunes)')
        ->and($byId['i_BM13C0VFU']['seconds'])->toBe(5351)
        ->and($byId['_0RYPhiIh44']['seconds'])->toBe(35 * 60 + 55)
        // Live agendada ("Em breve"): sem duração.
        ->and($byId['iSUMyXHZtGs']['seconds'])->toBeNull();
});

it('acusa a página do YouTube sem os dados', function () {
    ChannelParser::videos('<html><body>Algo mudou</body></html>');
})->throws(RuntimeException::class, 'ytInitialData');

it('reconhece o episódio pelo título e tira data e tema', function () {
    $parsed = telescopio()->parseTitle('Telescópio - 06/11/2026 - Ação voluntária - "Entrega do Amor"');

    expect($parsed['date']->format('Y-m-d'))->toBe('2026-11-06')
        ->and($parsed['topic'])->toBe('Ação voluntária - "Entrega do Amor')
        ->and(telescopio()->parseTitle('Telescópio  02/11/2026 - Dia de finados')['topic'])->toBe('Dia de finados')
        ->and(telescopio()->parseTitle('TELESCÓPIO com GUSTAVO CARMONA e DAVID GUERRA (06/08/26)'))->toBeNull()
        ->and(telescopio()->parseTitle('Copa TVitapê 2026 - Masculino - Oitavas'))->toBeNull();
});

it('reconhece os episódios do Jornal do Meio-Dia e da Manhã da Pop, sem tema no título', function () {
    $programs = Program::all();
    $jornal = $programs['jornal-do-meio-dia']->parseTitle('Jornal do Meio-Dia - AO VIVO 🔴 - Rádio Super Difusora 94,5 FM - 06/11/2026');
    $pop = $programs['manha-da-pop'];

    expect($jornal['date']->format('Y-m-d'))->toBe('2026-11-06')
        ->and($jornal['topic'])->toBe('')
        ->and($programs['jornal-do-meio-dia']->parseTitle('Unimed Sul Paulista no Manhã Super Difusora de 11/11/2026'))->toBeNull()
        ->and($pop->parseTitle('MANHÃ DA POP  -  06/11/2026')['date']->format('Y-m-d'))->toBe('2026-11-06')
        ->and($pop->parseTitle('MANHÃ DA POP  |  20/10/2026 ll')['date']->format('Y-m-d'))->toBe('2026-10-20')
        ->and($pop->parseTitle('MANHÃ DA POP 15/10/2026 II'))->not->toBeNull()
        ->and($pop->parseTitle('APURAÇÃO DOS VOTOS  |  25/10/2026'))->toBeNull();
});

it('manda a data por extenso e a duração para o Gemini', function () {
    $input = VideoStory::input(telescopio(), 'Telescópio - 06/11/2026 - Teste', CarbonImmutable::parse('2026-11-06'), 5351);

    expect($input['data'])->toBe('sexta-feira, 6 de novembro de 2026')
        ->and($input['duracao'])->toBe('1:29:11')
        ->and(VideoStory::input(telescopio(), 'x', CarbonImmutable::parse('2026-11-07'), 2296)['data'])->toBe('sábado, 7 de novembro de 2026')
        ->and(VideoStory::clock(2296))->toBe('38:16');
});

it('aceita a notícia válida e calcula o segundo de cada momento', function () {
    $article = checkArticle(videoArticle());

    expect(array_column($article['momentos'], 'segundos'))->toBe([90, 900, 2400, 3900])
        // Participante sem nome fica só com a função.
        ->and($article['participantes'][0])->toBe(['nome' => '', 'papel' => 'apresentador']);
});

it('recusa telefone, chave Pix, e-mail e perfil no texto', function (string $text) {
    checkArticle(videoArticle(['fechamento' => $text]));
})->throws(InvalidArgumentException::class, 'Dado de contato')->with([
    'telefone' => 'Doações pelo (15) 99649-8734.',
    'e-mail' => 'Contato: ajuda@exemplo.com.br.',
    'perfil' => 'Siga @entregadeamor no Instagram.',
    'site' => 'Mais em www.exemplo.com.br.',
]);

it('recusa o dia da semana errado', function () {
    checkArticle(videoArticle(['lide' => 'No programa de quinta-feira (3), protetores pediram castração.']));
})->throws(InvalidArgumentException::class, 'Dia da semana errado');

it('descarta só o momento inválido ou depois do fim do vídeo', function () {
    $article = checkArticle(videoArticle(['momentos' => [
        ['inicio' => '01:00', 'assunto' => 'a'],
        ['inicio' => '10:10:00', 'assunto' => 'depois do fim'],
        ['inicio' => 'meia hora', 'assunto' => 'inválido'],
        ['inicio' => '02:00', 'assunto' => ''],
    ]]));

    expect($article['momentos'])->toBe([['inicio' => '01:00', 'segundos' => 60, 'assunto' => 'a']]);
});

it('recusa a notícia sem nenhum momento válido', function (array $moments) {
    checkArticle(videoArticle(['momentos' => $moments]));
})->throws(InvalidArgumentException::class, 'momentos válidos')->with([
    'só depois do fim' => [[['inicio' => '10:10:00', 'assunto' => 'a']]],
    'só inválido' => [[['inicio' => 'meia hora', 'assunto' => 'a']]],
    'nenhum' => [[]],
]);

it('aceita momentos com intervalos longos e mostra em ordem de tempo', function () {
    // Os momentos seguem a ordem do texto (pirâmide invertida), não a do vídeo.
    $article = checkArticle(videoArticle(['momentos' => [
        ['inicio' => '1:28:30', 'assunto' => 'Anúncio no fim'], ['inicio' => '05:00', 'assunto' => 'Abertura'],
    ]]));

    expect(array_column($article['momentos'], 'inicio'))->toBe(['05:00', '1:28:30']);
});
