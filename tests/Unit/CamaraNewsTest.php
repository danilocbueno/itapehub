<?php

use App\Camara\Article;
use App\Camara\Names;
use App\Camara\Session;
use App\Camara\SessionCover;
use App\Camara\SiscamParser;
use App\Camara\Story;
use App\Camara\TemplateWriter;

function camaraSession(): Session
{
    $parser = new SiscamParser('https://itapetininga.siscam.com.br');
    $session = $parser->session(1608, fixtureContents('siscam-sessao.html'));
    $session->agenda = $parser->agenda(fixtureContents('siscam-roteiro.html'));

    return Names::fromConfig()->apply($session);
}

function validArticle(array $overrides = []): array
{
    return array_replace([
        'titulo' => 'Vereadores pedem reparos em estradas rurais',
        'linha_fina' => 'Na sessão de 29 de outubro, a Câmara aprovou 11 pedidos à Prefeitura.',
        'resumo' => ['A Câmara aprovou a urgência de dois projetos da Prefeitura.'],
        'secoes' => [['tema' => 'Ruas e estradas', 'itens' => ['Manutenção das estradas rurais do Viracopos (Miguel Arcanjo).']]],
        'tambem' => ['Presença: 18 dos 19 vereadores.'],
        'omitidos' => [],
    ], $overrides);
}

describe('Nomes curtos dos vereadores', function () {
    it('troca o nome do SisCam pelo nome curto, inclusive em autoria com vários nomes', function () {
        $names = Names::fromConfig();

        expect($names->short('Eduardo Vinícius Venturelli de Almeida Prando'))->toBe('Eduardo Prando')
            ->and($names->authors('Marina Nalesso Mariano, Júlia Nunes Machado'))->toBe('Marina Nalesso, Júlia Machado')
            ->and($names->short('Chefe do Poder Executivo'))->toBe('Prefeitura')
            ->and($names->short('Alguém Desconhecido'))->toBe('Alguém Desconhecido');
    });

    it('aplica os nomes curtos em presença, votações e roteiro', function () {
        $session = camaraSession();

        expect($session->attendance[0]['names'])->toContain('Catarina Motta')
            ->and($session->votes[0]['author'])->toBe('Prefeitura')
            ->and($session->agenda[6]['items'][0]['author'])->toBe('Itamar Martins');
    });
});

describe('Conferência da notícia', function () {
    it('aceita uma notícia no formato pedido', function () {
        $article = Article::fromArray(validArticle(), Names::fromConfig()->known());

        expect($article->titulo)->toBe('Vereadores pedem reparos em estradas rurais')
            ->and($article->toArray()['secoes'][0]['tema'])->toBe('Ruas e estradas');
    });

    it('recusa notícia sem título, com resumo vazio ou longa demais', function (array $overrides) {
        Article::fromArray(validArticle($overrides));
    })->with([
        'sem título' => [['titulo' => '']],
        'resumo vazio' => [['resumo' => []]],
        'título longo' => [['titulo' => str_repeat('a', 121)]],
        'resumo não é lista' => [['resumo' => 'texto solto']],
    ])->throws(InvalidArgumentException::class);

    it('recusa pedido atribuído a quem não é vereador conhecido', function () {
        Article::fromArray(validArticle(['secoes' => [['tema' => 'Ruas', 'itens' => ['Asfalto na Vila X (Vereador Inventado).']]]]), Names::fromConfig()->known());
    })->throws(InvalidArgumentException::class, 'Autor desconhecido');

    it('aceita pedido com mais de um autor conhecido', function () {
        $article = Article::fromArray(validArticle(['secoes' => [['tema' => 'Meio ambiente', 'itens' => ['Ouvir o secretário de Meio Ambiente (Marina Nalesso, Eduardo Prando e Júlia Machado).']]]]), Names::fromConfig()->known());

        expect($article->secoes[0]['itens'])->toHaveCount(1);
    });
});

describe('Notícia pelo modelo fixo (sem IA)', function () {
    it('diz que só a urgência foi aprovada, não o projeto', function () {
        $article = (new TemplateWriter)->write(camaraSession());

        expect($article->resumo[0])->toStartWith('A Câmara aprovou a urgência do Projeto de Lei Complementar Nº 14/2026, da Prefeitura (17 votos a favor)')
            ->and(implode(' ', $article->resumo))->not->toContain('aprovou o Projeto');
    });

    it('lista os pedidos com o autor e sem a fórmula de ofício', function () {
        $article = (new TemplateWriter)->write(camaraSession());
        $requests = array_column($article->secoes, 'itens', 'tema');

        expect($article->titulo)->toBe('Vereadores fazem 21 pedidos na sessão de 29 de outubro')
            ->and($requests['Pedidos de informação e providências'])->toContain('Realização de serviços de manutenção, cascalhamento, nivelamento e patrolamento nas estradas rurais dos bairros Viracopos, Várzea, Capivari e Areião. (Miguel Arcanjo)')
            ->and($requests['Pedidos de serviços'])->toContain('Substituição de lâmpadas queimadas na Rua Júlio de mesquita filho 825- Vila Nova Itapetininga. (Bispo André)')
            ->toContain('Limpeza nos meios-fios e sarjetas da Rua Diamante, sentido Vale San Fernando (Pacaembu), devido alagamento e formação de lamas nos dias de chuva, dificultando a circulação de carros e motocicletas. (Eduardo Prando)')
            ->and(implode(' ', [...$requests['Pedidos de serviços'], ...$requests['Pedidos de informação e providências']]))->not->toContain('Solicit')
            ->not->toContain('DIRIGIDO');
    });

    it('fecha com respostas da Prefeitura e presença em horário legível', function () {
        $article = (new TemplateWriter)->write(camaraSession());

        expect($article->tambem)->toContain('A Prefeitura respondeu a 19 pedidos feitos em sessões anteriores.')
            ->and(end($article->tambem))->toBe('Presença: 18 vereadores; ausências: Cristiane Cardena (ausências justificadas). A sessão começou às 19h e terminou às 20h13.');
    });

    it('não leva as correspondências recebidas para o texto', function () {
        $article = (new TemplateWriter)->write(camaraSession());

        expect(json_encode($article->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain('anônimo')
            ->not->toContain('Ministério Público');
    });

    it('passa na própria conferência', function () {
        $article = (new TemplateWriter)->write(camaraSession());

        expect(Article::fromArray($article->toArray(), Names::fromConfig()->known())->titulo)->toBe($article->titulo);
    });
});

function validStory(array $overrides = []): array
{
    return array_replace([
        'titulo' => 'Câmara aprova urgência para regras de eventos',
        'linha_fina' => 'Vereadores também pediram reparos em estradas rurais.',
        'lide' => 'A Câmara aprovou na sessão de quinta-feira (29) a urgência de dois projetos da Prefeitura.',
        'blocos' => [['intertitulo' => 'Estradas', 'paragrafos' => ['O vereador Miguel Arcanjo pediu manutenção das estradas do Viracopos.']]],
        'fechamento' => '18 dos 19 vereadores estiveram presentes.',
        'omitidos' => [],
    ], $overrides);
}

describe('Notícia em texto corrido (Gemini)', function () {
    it('dá o cargo de cada vereador no gênero certo', function () {
        $councilors = Names::fromConfig()->councilors();

        expect($councilors['Marina Nalesso'])->toBe('vereadora')
            ->and($councilors['Miguel Arcanjo'])->toBe('vereador')
            ->and($councilors)->not->toHaveKey('Prefeitura');
    });

    it('aceita lide, blocos e fechamento', function () {
        $story = Story::fromArray(validStory(), Names::fromConfig()->councilors());

        expect($story->lide)->toStartWith('A Câmara aprovou')
            ->and($story->toArray()['blocos'][0]['intertitulo'])->toBe('Estradas');
    });

    it('aceita bloco sem intertítulo e vereadora no início da frase', function () {
        $story = Story::fromArray(validStory([
            'blocos' => [['intertitulo' => '', 'paragrafos' => ['Vereadora Marina Nalesso pediu informações ao hospital.']]],
        ]), Names::fromConfig()->councilors());

        expect($story->blocos[0]['intertitulo'])->toBe('');
    });

    it('recusa vereador que não está na lista', function () {
        Story::fromArray(validStory(['lide' => 'O vereador João Inventado pediu asfalto.']), Names::fromConfig()->councilors());
    })->throws(InvalidArgumentException::class, 'Vereador desconhecido no texto: João Inventado');

    it('recusa cargo no gênero errado', function () {
        Story::fromArray(validStory(['fechamento' => 'O vereador Marina Nalesso não falou.']), Names::fromConfig()->councilors());
    })->throws(InvalidArgumentException::class, 'Cargo no gênero errado no texto: vereador Marina Nalesso');

    it('recusa notícia sem lide ou sem blocos', function (array $overrides) {
        Story::fromArray(validStory($overrides));
    })->with([
        'sem lide' => [['lide' => '']],
        'sem blocos' => [['blocos' => []]],
        'bloco sem parágrafo' => [['blocos' => [['intertitulo' => 'X', 'paragrafos' => []]]]],
    ])->throws(InvalidArgumentException::class);
});

describe('Capa da notícia da sessão', function () {
    it('tira o número e o tipo da sessão do título', function (string $title, string $number, string $kind) {
        $cover = SessionCover::from($title, null);

        expect($cover->number)->toBe($number)
            ->and($cover->kind)->toBe($kind)
            ->and($cover->stats)->toBe([]);
    })->with([
        ['53ª Sessão Ordinária de 2026', '53ª', 'Sessão Ordinária'],
        ['12ª Sessão Extraordinária de 2026', '12ª', 'Sessão Extraordinária'],
        ['Sessão Solene', '', 'Sessão Solene'],
    ]);

    it('resume a sessão em números: pedidos, urgências, presença e duração', function () {
        $cover = SessionCover::from('53ª Sessão Ordinária de 2026', camaraSession());

        expect($cover->stats)->toBe(['21 pedidos', '2 urgências aprovadas', '18 de 19 vereadores', '1h13 de sessão']);
    });
});
