<?php

use App\Camara\SiscamParser;

beforeEach(function () {
    $this->parser = new SiscamParser('https://itapetininga.siscam.com.br');
});

describe('Lista de sessões do SisCam', function () {
    it('lê número, título, data, hora e observações das sessões recentes', function () {
        $sessions = $this->parser->sessions(fixtureContents('siscam-sessoes.html'));

        expect($sessions[0])->toBe([
            'id' => 1608,
            'title' => '53ª Sessão Ordinária de 2026',
            'date' => '2026-10-29',
            'time' => '19:00',
            'notes' => 'PL 131/2026; PLC 14/2026',
        ])->and($sessions[1]['notes'])->toBe('');
    });
});

describe('Página da sessão', function () {
    it('lê data, encerramento, presença e o link do roteiro', function () {
        $session = $this->parser->session(1608, fixtureContents('siscam-sessao.html'));

        expect($session->title)->toBe('53ª Sessão Ordinária de 2026')
            ->and($session->date)->toBe('2026-10-29')
            ->and($session->closedAt)->toBe('20:13')
            ->and($session->complete())->toBeTrue()
            ->and($session->agendaUrl)->toBe('https://itapetininga.siscam.com.br/Sessoes/Documento/122853')
            ->and($session->attendance[0]['label'])->toBe('Presentes')
            ->and($session->attendance[0]['count'])->toBe(18)
            ->and($session->attendance[0]['names'])->toHaveCount(18)
            ->and($session->attendance[1]['names'])->toBe(['Cristiane de Fátima Cardena']);
    });

    it('lê cada votação com a fase, o resultado e os votos nominais', function () {
        $session = $this->parser->session(1608, fixtureContents('siscam-sessao.html'));
        $first = $session->votes[0];

        expect($session->votes)->toHaveCount(13)
            ->and($first['title'])->toBe('Projeto de Lei Complementar Nº 14/2026 - Executivo')
            ->and($first['author'])->toBe('Chefe do Poder Executivo')
            ->and($first['subject'])->toStartWith('Dispõe sobre a expedição de Alvará')
            ->and($first['phase'])->toBe('Socilitando regime de urgência')
            ->and($first['result'])->toBe('Aprovado')
            ->and($first['tallies'][0]['label'])->toBe('A favor')
            ->and($first['tallies'][0]['count'])->toBe(17)
            ->and($first['tallies'][0]['names'])->toHaveCount(17);
    });

    it('não confunde a presença com os votos', function () {
        $session = $this->parser->session(1608, fixtureContents('siscam-sessao.html'));

        expect(array_column($session->attendance, 'label'))->toBe(['Presentes', 'Ausências Justificadas']);
    });
});

describe('Roteiro da sessão', function () {
    it('separa as seções do roteiro com seus documentos', function () {
        $agenda = $this->parser->agenda(fixtureContents('siscam-roteiro.html'));

        expect(array_column($agenda, 'section'))->toBe(['Respostas', 'Correspondencia Recebida', 'Novos Projetos', 'Ordem do Dia', 'Moções', 'Indicações', 'Requer. Informações'])
            ->and($agenda[0]['items'])->toHaveCount(19)
            ->and($agenda[6]['items'][0])->toBe([
                'title' => 'Requerimento Nº 701/2026',
                'url' => 'https://itapetininga.siscam.com.br/Documentos/Documento/122889',
                'author' => 'Itamar José Martins',
                'text' => $agenda[6]['items'][0]['text'],
            ])
            ->and($agenda[6]['items'][0]['text'])->toStartWith('Solicita ao Executivo Municipal');
    });

    it('lê documentos com mais de um autor', function () {
        $agenda = $this->parser->agenda(fixtureContents('siscam-roteiro.html'));
        $requests = array_column($agenda[6]['items'], null, 'title');

        expect($requests['Requerimento Nº 703/2026']['author'])->toBe('Marina Nalesso Mariano, Eduardo Vinícius Venturelli de Almeida Prando, Júlia Nunes Machado')
            ->and($requests['Requerimento Nº 703/2026']['text'])->toStartWith('Solicita a convocação do Secretário');
    });
});

describe('Autoria dos documentos', function () {
    it('separa autor e texto', function (string $raw, string $author, string $text) {
        expect(SiscamParser::splitAuthor($raw))->toBe([$author, $text]);
    })->with([
        'vereador e ponto' => ['de autoria do Vereador Bispo André. Solicita a troca de lâmpadas.', 'Bispo André', 'Solicita a troca de lâmpadas.'],
        'executivo e vírgula' => ['de autoria de Chefe do Poder Executivo, Dispõe sobre feiras.', 'Chefe do Poder Executivo', 'Dispõe sobre feiras.'],
        'resposta' => ['de autoria do Vereador Bispo André, enviado por Chefe do Poder Executivo.', 'Bispo André', 'enviado por Chefe do Poder Executivo.'],
        'sem autor' => ['de autoria de . Recebimento de comunicante anônimo.', '', 'Recebimento de comunicante anônimo.'],
        'órgão repetido no texto' => ['de autoria de Assembleia Legislativa. Assembleia Legislativa, por meio do Deputado X.', 'Assembleia Legislativa', 'Assembleia Legislativa, por meio do Deputado X.'],
        'sem a expressão' => ['Moção de congratulações.', '', 'Moção de congratulações.'],
    ]);
});
