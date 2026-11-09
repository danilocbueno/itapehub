<?php

use App\Camara\SessionRepository;
use App\Camara\SiscamParser;
use App\Data\Item;
use App\Pipeline\Clusterer;
use App\Programas\VideoStory;
use App\Storage\ItemRepository;
use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

beforeEach(function () {
    fakeSources();
    $this->artisan('collect')->assertSuccessful();
    $this->artisan('collect')->assertSuccessful();
});

function built(string $path): string
{
    return file_get_contents(config('itape.paths.build').'/'.$path);
}

it('gera home, arquivo, fontes, 404 e feed', function () {
    $this->artisan('build')->assertSuccessful();

    foreach (['index.html', 'arquivo/index.html', 'arquivo/2026/10/25/index.html', 'fontes/index.html', '404.html', 'feed.xml', '.nojekyll'] as $path) {
        expect(config('itape.paths.build').'/'.$path)->toBeFile();
    }
});

it('mostra notícias, tempo e alerta na home, sem filtro por fonte', function () {
    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    expect($html)
        ->toContain('Chuva passa de 110 mm em Itapetininga e deixa desabrigados')
        ->toContain('class="weather"')
        ->toContain('class="alerts"')
        ->not->toContain('data-source=')
        ->not->toContain('class="filters"')
        ->toContain('<h2 data-day="2026-10-24">Ontem</h2>')
        ->toContain('referrerpolicy="no-referrer"')
        ->toContain('https://wa.me/?text=');
});

it('separa o resto do primeiro dia dos próximos eventos com o nome do dia', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    $item = new Item('jornal', 'https://jornal.test/show', 'Show na Praça', 'O show acontece no dia 31 de outubro, às 20h, na Praça.', publishedAt: CarbonImmutable::parse('2026-10-25 16:30'));
    $item->cluster = $item->id();
    $repository->add($item);
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');
    $header = strpos($html, '<h2 data-day="2026-10-25">Hoje</h2>');

    expect(strpos($html, '<section class="upcoming"'))->not->toBeFalse()
        ->and($header)->toBeGreaterThan(strpos($html, '</section>', strpos($html, '<section class="upcoming"')))
        ->and($header)->toBeLessThan(strpos($html, '<div class="cards">'))
        ->and(substr_count($html, '<h2 data-day="2026-10-25">'))->toBe(1);
});

it('põe o link da agenda na linha do título dos próximos eventos', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    $item = new Item('jornal', 'https://jornal.test/show', 'Show na Praça', 'O show acontece no dia 31 de outubro, às 20h, na Praça.', publishedAt: CarbonImmutable::parse('2026-10-25 16:30'));
    $item->cluster = $item->id();
    $repository->add($item);
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $head = Str::betweenFirst(built('index.html'), '<div class="upcoming-head">', '</div>');

    expect($head)->toContain('<h2>Próximos eventos</h2>')
        ->toContain('<a class="upcoming-more" href="/agenda/">Ver agenda completa</a>')
        ->and(substr_count(built('index.html'), 'class="upcoming-more"'))->toBe(1);
});

it('não põe separador no primeiro dia quando não há próximos eventos', function () {
    config(['itape.paths.data' => $this->tmp.'/sem-eventos']);
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach (range(1, 5) as $n) {
        $item = new Item('jornal', "https://jornal.test/{$n}", "Notícia número {$n} da cidade", publishedAt: CarbonImmutable::parse("2026-10-25 1{$n}:00"));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->not->toContain('<section class="upcoming"')
        ->not->toContain('<h2 data-day="2026-10-25">');
});

it('junta a mesma notícia de fontes diferentes em um card', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    $first = new Item('jornal', 'https://jornal.test/rio', 'Rio Itapetininga recua e Prefeitura inicia limpeza de áreas alagadas', publishedAt: CarbonImmutable::parse('2026-10-25 09:00'));
    $second = new Item('correio', 'https://correio.test/rio', 'Rio Itapetininga recua, mas três áreas seguem alagadas', publishedAt: CarbonImmutable::parse('2026-10-25 11:00'));
    (new Clusterer)->assign($first, [], 48);
    (new Clusterer)->assign($second, [$first], 48);
    $repository->add($first);
    $repository->add($second);
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    expect($html)->toContain('Também em:')
        ->toContain('href="https://correio.test/rio"')
        ->and(substr_count($html, 'Rio Itapetininga recua'))->toBe(1);
});

it('gera um feed RSS válido', function () {
    $this->artisan('build')->assertSuccessful();
    $feed = simplexml_load_string(built('feed.xml'));

    expect($feed)->not->toBeFalse()
        ->and(count($feed->channel->item))->toBeGreaterThan(10)
        ->and((string) $feed->channel->item[0]->link)->toStartWith('http');
});

it('mostra a situação das fontes', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('fontes/index.html'))
        ->toContain('Correio de Itapetininga')
        ->toContain('de hora em hora, das 7h às 23h')
        ->toContain('<span class="ok">OK</span>');
});

it('mostra o cabeçalho de jornal com o nome e o subtítulo, sem a data por extenso com o ano', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))
        ->toContain('class="masthead"')
        ->toContain('<span class="brand-a">Itapê')
        ->toContain('<span class="brand-b">Hub')
        ->toContain('Tudo o que acontece na cidade, num só lugar')
        ->not->toContain('masthead-top')
        ->not->toContain('Itapetininga, domingo, 25 de outubro de 2026');
});

it('desenha a linha do logo com quatro paradas e o anel no fim', function () {
    $this->artisan('build')->assertSuccessful();
    $brand = Str::between(built('index.html'), '<a class="brand"', '</a>');

    expect(substr_count(Str::between($brand, 'brand-a', 'brand-b'), '<i></i>'))->toBe(4)
        ->and($brand)->toContain('<span class="brand-b">Hub<span class="brand-line" aria-hidden="true"></span>');
});

it('usa o terminal da linha como favicon', function () {
    $this->artisan('build')->assertSuccessful();
    $icon = Str::between(built('index.html'), '<link rel="icon" href="', '">');

    expect(rawurldecode($icon))->toContain("<circle cx='64' cy='50' r='18'")
        ->toContain("stroke='#b42318'");
});

it('usa o nome configurado no cabeçalho, sem destacar parte dele', function () {
    config(['itape.site.name' => 'Giro Itapê']);

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('>Giro Itapê</a>');
});

it('não mostra na previsão dias que já passaram', function () {
    JsonFile::write(config('itape.paths.data').'/weather.json', [
        'fetched_at' => '2026-10-24T20:00:00-03:00',
        'current' => ['temperature' => 18.5, 'code' => 53, 'time' => '2026-10-24T20:00'],
        'daily' => [
            ['date' => '2026-10-24', 'max' => 21, 'min' => 16, 'rain' => 9, 'rain_probability' => 94, 'code' => 53],
            ['date' => '2026-10-25', 'max' => 26, 'min' => 16, 'rain' => 4, 'rain_probability' => 83, 'code' => 3],
            ['date' => '2026-10-26', 'max' => 25, 'min' => 16, 'rain' => 4, 'rain_probability' => 88, 'code' => 61],
        ],
    ]);

    $this->artisan('build')->assertSuccessful();
    $weather = Str::between(built('index.html'), 'class="weather"', '</section>');

    expect($weather)->not->toContain('Sáb 24')
        ->toContain('Hoje')
        ->toContain('Amanhã')
        ->toContain('26° / 16°');
});

it('mostra a previsão um dia de cada vez, começando por hoje', function () {
    JsonFile::write(config('itape.paths.data').'/weather.json', [
        'fetched_at' => '2026-10-25T16:00:00-03:00',
        'current' => ['temperature' => 22.7, 'code' => 95, 'time' => '2026-10-25T16:00'],
        'daily' => [
            ['date' => '2026-10-25', 'max' => 26, 'min' => 16, 'rain' => 0, 'rain_probability' => 86, 'code' => 1],
            ['date' => '2026-10-26', 'max' => 27, 'min' => 16, 'rain' => 4, 'rain_probability' => 88, 'code' => 53],
            ['date' => '2026-10-27', 'max' => 25, 'min' => 15, 'rain' => 1, 'rain_probability' => 87, 'code' => 3],
        ],
    ]);

    $this->artisan('build')->assertSuccessful();
    $weather = Str::between(built('index.html'), 'class="weather"', '</section>');
    preg_match_all('~<div class="weather-slide( is-active)?"( hidden)?>\s*<b>([^<]+)</b>~', $weather, $slides, PREG_SET_ORDER);

    expect($weather)->toContain('data-rotate')
        ->and(array_column($slides, 3))->toBe(['Hoje', 'Amanhã', 'Ter 27'])
        ->and($slides[0][1])->toBe(' is-active')
        ->and($slides[0][2])->toBe('')
        ->and($slides[1][2])->toBe(' hidden')
        ->and(substr_count($weather, 'class="wx-icon wx-'))->toBeGreaterThanOrEqual(3)
        ->and($weather)->toContain('wx-sun')
        ->toContain('wx-rain');
});

it('mostra hoje com máxima e mínima, como os outros dias, sem a temperatura atual', function () {
    JsonFile::write(config('itape.paths.data').'/weather.json', [
        'fetched_at' => '2026-10-25T16:00:00-03:00',
        'current' => ['temperature' => 22.7, 'code' => 95, 'time' => '2026-10-25T16:00'],
        'daily' => [
            ['date' => '2026-10-25', 'max' => 26, 'min' => 16, 'rain' => 0, 'rain_probability' => 86, 'code' => 1],
        ],
    ]);

    $this->artisan('build')->assertSuccessful();
    $today = Str::betweenFirst(built('index.html'), '<div class="weather-slide is-active">', '</div>');

    expect($today)->toContain('26° / 16°')
        ->toContain('wx-sun')
        ->not->toContain('23°')
        ->not->toContain('agora')
        ->not->toContain('wx-storm');
});

it('mostra o aviso do INMET como faixa com rótulo', function () {
    $this->artisan('build')->assertSuccessful();

    expect(Str::between(built('index.html'), 'class="alerts"', '</section>'))
        ->toContain('Aviso do INMET');
});

it('mostra vários avisos do INMET em uma faixa só', function () {
    JsonFile::write(config('itape.paths.data').'/alerts.json', ['fetched_at' => '2026-10-25T16:00:00-03:00', 'alerts' => [
        ['event' => 'Chuvas Intensas', 'severity' => 'Perigo Potencial', 'level' => 1, 'start' => '2026-10-25T00:00:00-03:00', 'end' => '2026-10-25T23:59:00-03:00', 'description' => '', 'link' => 'https://avisos.inmet.gov.br/1'],
        ['event' => 'Tempestade', 'severity' => 'Perigo Potencial', 'level' => 1, 'start' => '2026-10-25T00:00:00-03:00', 'end' => '2026-10-25T23:59:00-03:00', 'description' => '', 'link' => 'https://avisos.inmet.gov.br/2'],
    ]]);

    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    expect(substr_count($html, 'class="alert '))->toBe(1)
        ->and($html)->toContain('Chuvas intensas e tempestade na região de Itapetininga até hoje às 23h59.');
});

it('mostra a data e a previsão no cabeçalho, ao lado do nome', function () {
    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');
    $title = Str::betweenFirst($html, '<div class="masthead-title">', '<nav class="topics"');

    expect($title)->toContain('<span class="masthead-date">Domingo, 25 de outubro</span>')
        ->toContain('<div class="masthead-weather"><section class="weather"')
        ->and(strpos($title, '<div class="masthead-brand">'))->toBeLessThan(strpos($title, 'masthead-date'))
        ->and(strpos($html, '<section class="alerts"'))->toBeLessThan(strpos($html, '<header class="masthead"'))
        ->and(built('fontes/index.html'))->not->toContain('class="masthead-date"')
        ->not->toContain('class="masthead-weather"');
});

it('mostra a condição do tempo e a chuva também para leitores de tela', function () {
    $this->artisan('build')->assertSuccessful();
    $weather = Str::between(built('index.html'), 'class="weather"', '</section>');

    expect($weather)->toContain('<span class="visually-hidden">chuva </span>')
        ->toMatch('~<span class="wx" title="[^"]+">.*?<span class="visually-hidden">[a-zç ]+</span></span>~s');
});

it('mostra o aviso do INMET no início da página, antes do cabeçalho', function () {
    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    expect(strpos($html, '<section class="alerts"'))->toBeLessThan(strpos($html, '<header class="masthead"'))
        ->and(built('fontes/index.html'))->not->toContain('<section class="alerts"');
});

it('completa a capa com notícias do dia anterior quando hoje há menos de três', function () {
    config(['itape.paths.data' => $this->tmp.'/capa']);
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/hoje', 'Única notícia de hoje', '2026-10-25 09:00'],
        ['https://jornal.test/ontem-1', 'Primeira de ontem', '2026-10-24 20:00'],
        ['https://jornal.test/ontem-2', 'Segunda de ontem', '2026-10-24 18:00'],
        ['https://jornal.test/ontem-3', 'Terceira de ontem', '2026-10-24 15:00'],
    ] as [$url, $title, $date]) {
        $item = new Item('jornal', $url, $title, publishedAt: CarbonImmutable::parse($date));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');
    $front = Str::betweenFirst($html, '<div class="front">', '<!-- /front -->');

    expect(substr_count($front, '<article class="card'))->toBe(3)
        ->and($front)->toContain('Única notícia de hoje')
        ->toContain('Primeira de ontem')
        ->toContain('Segunda de ontem')
        ->not->toContain('Terceira de ontem')
        ->and(Str::after($html, '<!-- /front -->'))->toContain('<h2 data-day="2026-10-24">Ontem</h2>')
        ->toContain('Terceira de ontem')
        ->and($html)->not->toContain('<h2 data-day="2026-10-25">');
});

it('monta a capa com a manchete e duas notícias ao lado', function () {
    $this->artisan('build')->assertSuccessful();
    $front = Str::between(built('index.html'), '<div class="front">', '<!-- /front -->');

    expect(substr_count($front, 'class="card lead'))->toBe(1)
        ->and(substr_count($front, 'class="card side'))->toBe(2);
});

it('não repete nenhuma notícia na home', function () {
    $this->artisan('build')->assertSuccessful();
    preg_match_all('~<h3><a href="([^"]+)"~', built('index.html'), $links);

    expect($links[1])->not->toBeEmpty()
        ->and(count($links[1]))->toBe(count(array_unique($links[1])));
});

it('destaca só a primeira notícia da home', function () {
    $this->artisan('build')->assertSuccessful();

    expect(substr_count(built('index.html'), 'class="card lead'))->toBe(1)
        ->and(built('arquivo/2026/10/25/index.html'))->not->toContain('class="card lead');
});

it('não reaproveita a classe da manchete em outros elementos', function () {
    $css = file_get_contents(resource_path('assets/site.css'));

    // A classe .lead é só da manchete; um seletor solto ".lead {" pintaria a manchete de cinza.
    expect($css)->not->toMatch('/^\.lead \{/m');
});

it('esconde o espaço da foto quando a imagem da fonte não carrega', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('onerror="this.closest(\'.card\').classList.add(\'noimg\'); this.parentNode.remove()"');
});

it('mostra fonte, horário e selo oficial na linha de contexto', function () {
    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    expect($html)->toContain('<span class="kicker-source">Prefeitura</span>')
        ->toContain('<span class="official">oficial</span>');
});

it('publica as fontes no próprio site', function () {
    $this->artisan('build')->assertSuccessful();

    foreach (['newsreader.woff2', 'newsreader-italic.woff2', 'ibm-plex-sans.woff2'] as $font) {
        expect(config('itape.paths.build').'/assets/fonts/'.$font)->toBeFile();
    }

    expect(built('index.html'))
        ->toContain('@font-face { font-family: "Newsreader"; src: url("/assets/fonts/newsreader.woff2")')
        ->not->toContain('fonts.googleapis.com');
});

it('usa o caminho base do GitHub Pages também nas fontes', function () {
    config(['itape.site.base' => '/itapenews']);

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('url("/itapenews/assets/fonts/ibm-plex-sans.woff2")');
});

it('mostra na página de fontes o erro de uma fonte que falhou', function () {
    $state = JsonFile::read(config('itape.paths.data').'/state.json');
    $state['sources']['jornal'] = [...$state['sources']['jornal'], 'errors' => 2, 'last_error' => 'HTTP 503'];
    JsonFile::write(config('itape.paths.data').'/state.json', $state);

    $this->artisan('build')->assertSuccessful();

    expect(built('fontes/index.html'))
        ->toContain('<span class="bad">Erro (2x)</span>')
        ->toContain('HTTP 503');
});

it('mostra o arquivo como um calendário por mês, com link nos dias que têm notícias', function () {
    $this->artisan('build')->assertSuccessful();
    $page = built('arquivo/index.html');
    $october = Str::betweenFirst($page, '<table class="calendar">', '</table>');

    expect($page)->toContain('Outubro de 2026')
        ->and($october)->toContain('<th scope="col">Dom</th>')
        ->toContain('<th scope="col">Sáb</th>')
        ->toMatch('~<td class="cal-day cal-today">\\s*<a href="/arquivo/2026/10/25/" aria-label="25: \\d+ notícias?">~')
        ->toContain('<td class="cal-empty"><span class="cal-num">1</span></td>')
        ->toContain('<td class="cal-out"></td>');
});

it('gera uma página para cada tema', function () {
    $this->artisan('build')->assertSuccessful();

    foreach (array_keys(config('themes')) as $slug) {
        expect(config('itape.paths.build')."/tema/{$slug}/index.html")->toBeFile();
    }
});

it('lista na página do tema só as notícias daquele assunto', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/acidente', 'Colisão entre caminhão e carro interdita rodovia em Itapetininga'],
        ['https://jornal.test/teatro', 'Teatro Literário apresenta peça na Biblioteca Municipal'],
    ] as [$url, $title]) {
        $item = new Item('jornal', $url, $title, publishedAt: CarbonImmutable::parse('2026-10-25 10:00'));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $page = built('tema/transito/index.html');

    expect($page)->toContain('<h1 class="page-title">Trânsito</h1>')
        ->toContain('href="https://jornal.test/acidente"')
        ->not->toContain('href="https://jornal.test/teatro"');
});

it('mostra a linha de assuntos em todas as páginas e marca o tema atual', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('<nav class="topics"')
        ->toContain('href="/tema/outras/"')
        ->and(built('fontes/index.html'))->toContain('<nav class="topics"')
        ->and(built('tema/clima/index.html'))->toContain('href="/tema/clima/" aria-current="page"');
});

it('mostra o tema no card com link para a página do tema', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/acidente', 'Colisão entre caminhão e carro interdita rodovia em Itapetininga'],
        ['https://jornal.test/cpfl', 'CPFL Santa Cruz lança campanha de bônus para clientes'],
    ] as [$url, $title]) {
        $item = new Item('jornal', $url, $title, publishedAt: CarbonImmutable::parse('2026-10-25 10:00'));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $card = fn (string $html, string $url) => Str::before(Str::afterLast(Str::before($html, 'href="'.$url.'"'), '<article'), '</article>');

    expect($card(built('index.html'), 'https://jornal.test/acidente'))->toContain('<a class="kicker-theme" href="/tema/transito/">Trânsito</a>')
        ->and($card(built('index.html'), 'https://jornal.test/cpfl'))->not->toContain('kicker-theme')
        ->and($card(built('tema/transito/index.html'), 'https://jornal.test/acidente'))->not->toContain('kicker-theme');
});

it('lista em Outras as notícias sem tema', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/acidente', 'Colisão entre caminhão e carro interdita rodovia em Itapetininga'],
        ['https://jornal.test/cpfl', 'CPFL Santa Cruz lança campanha de bônus para clientes'],
    ] as [$url, $title]) {
        $item = new Item('jornal', $url, $title, publishedAt: CarbonImmutable::parse('2026-10-25 10:00'));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();

    expect(built('tema/outras/index.html'))->toContain('href="https://jornal.test/cpfl"')
        ->not->toContain('href="https://jornal.test/acidente"');
});

it('avisa quando um tema ainda não tem notícias', function () {
    config(['itape.paths.data' => $this->tmp.'/vazio']);

    $this->artisan('build')->assertSuccessful();

    expect(built('tema/esportes/index.html'))->toContain('Ainda não há notícias de esportes.');
});

it('gera a página do resumo do dia com o texto pronto para o WhatsApp', function () {
    $this->artisan('build')->assertSuccessful();
    $page = built('hoje/index.html');
    $text = html_entity_decode(Str::between($page, '<textarea', '</textarea>'));

    expect($text)->toContain('*ItapêHub · domingo, 25/10*')
        ->toContain('• ')
        ->toContain('Leia tudo em: http://localhost:8000/')
        ->and($page)->toContain('data-copy="digest-text"')
        ->toContain('href="https://wa.me/?text=%2AItap%C3%AAHub');
});

it('leva ao resumo do dia pelo rodapé de todas as páginas', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('href="/hoje/">Resumo do dia</a>')
        ->and(built('fontes/index.html'))->toContain('href="/hoje/">Resumo do dia</a>');
});

it('gera a agenda com um card por evento: data, texto da notícia e link', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/festival', 'Festival de música agita a Praça', 'O festival acontece no dia 31 de outubro, às 20h, na Praça.'],
        ['https://jornal.test/feira', 'Feira de artesanato no Centro', 'A feira acontece no dia 27 de outubro na Praça.'],
    ] as [$url, $title, $summary]) {
        $item = new Item('jornal', $url, $title, $summary, publishedAt: CarbonImmutable::parse('2026-10-24 10:00'));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $page = built('agenda/index.html');
    // O card do festival, localizado pelo link (outros eventos reais dos dados de teste podem vir antes).
    $at = strpos($page, 'href="https://jornal.test/festival"');
    $start = strrpos(substr($page, 0, $at), '<article');
    $festival = substr($page, $start, strpos($page, '</article>', $at) - $start);

    expect($page)->toContain('<h1 class="page-title">Agenda</h1>')
        ->toContain('confira na notícia')
        // Os dados de teste já trazem um evento real (Corrida da Prevenção) além dos dois criados aqui.
        ->toContain(substr_count($page, '<article class="agenda-item').' eventos nos próximos 14 dias')
        ->and(strpos($page, 'jornal.test/feira'))->toBeLessThan(strpos($page, 'jornal.test/festival'))
        ->and($festival)->toContain('<span class="agenda-weekday">Sáb</span>')
        ->toContain('<span class="agenda-day-number">31</span>')
        ->toContain('<span class="agenda-month">out</span>')
        ->toContain('20h')
        ->toContain('O festival acontece no dia 31 de outubro')
        ->toContain('Ler na fonte')
        ->and($page)->toContain('horário a confirmar');
});

it('mostra os próximos eventos na home, logo depois da capa', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    $item = new Item('jornal', 'https://jornal.test/festival', 'Festival de música agita a Praça', 'O festival acontece no dia 31 de outubro, às 20h, na Praça.', publishedAt: CarbonImmutable::parse('2026-10-24 10:00'));
    $item->cluster = $item->id();
    $repository->add($item);
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');
    $block = Str::between($html, '<section class="upcoming"', '</section>');

    expect($block)->toContain('Festival de música agita a Praça')
        ->toContain('20h')
        ->toContain('href="/agenda/"')
        ->and(strpos($html, '<!-- /front -->'))->toBeLessThan(strpos($html, '<section class="upcoming"'));
});

it('não mostra o bloco de eventos na home quando não há eventos', function () {
    // As cópias reais das fontes têm um evento (Corrida da Prevenção); aqui os dados ficam vazios.
    config(['itape.paths.data' => $this->tmp.'/vazio']);

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->not->toContain('<section class="upcoming"');
});

it('mostra a imagem dos eventos na agenda e no bloco da home', function () {
    $repository = new ItemRepository(config('itape.paths.data').'/items');
    foreach ([
        ['https://jornal.test/festival', 'Festival de música agita a Praça', 'https://img.test/festival.jpg'],
        ['https://jornal.test/feira', 'Feira de artesanato no Centro', null],
    ] as [$url, $title, $image]) {
        $item = new Item('jornal', $url, $title, 'O evento acontece no dia 31 de outubro, às 20h, na Praça.', $image, publishedAt: CarbonImmutable::parse('2026-10-24 10:00'));
        $item->cluster = $item->id();
        $repository->add($item);
    }
    $repository->save();

    $this->artisan('build')->assertSuccessful();
    // O <article> inteiro que contém o link da notícia.
    $agendaItem = function (string $html, string $url): string {
        $at = strpos($html, 'href="'.$url.'"');
        $start = strrpos(substr($html, 0, $at), '<article');

        return substr($html, $start, strpos($html, '</article>', $at) - $start);
    };
    $block = Str::between(built('index.html'), '<section class="upcoming"', '</section>');

    expect($agendaItem(built('agenda/index.html'), 'https://jornal.test/festival'))->toContain('<img src="https://img.test/festival.jpg"')
        ->and($agendaItem(built('agenda/index.html'), 'https://jornal.test/feira'))->not->toContain('<img')
        ->and($block)->toContain('<img src="https://img.test/festival.jpg"');
});

it('avisa quando não há eventos nos próximos dias', function () {
    config(['itape.paths.data' => $this->tmp.'/vazio']);

    $this->artisan('build')->assertSuccessful();

    expect(built('agenda/index.html'))->toContain('Nenhum evento encontrado para os próximos 14 dias.');
});

it('leva à agenda pelo primeiro item da linha de assuntos e pelo rodapé', function () {
    $this->artisan('build')->assertSuccessful();
    $html = built('index.html');

    $topics = Str::between($html, '<nav class="topics"', '</nav>');

    expect($topics)->toContain('href="/agenda/"')
        ->and(strpos($topics, 'href="/agenda/"'))->toBeLessThan(strpos($topics, 'href="/tema/empregos/"'))
        ->and(Str::between($html, '<footer', '</footer>'))->toContain('href="/agenda/">Agenda</a>');
});

it('usa o caminho base do GitHub Pages nos links', function () {
    config(['itape.site.base' => '/itapenews']);

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('href="/itapenews/arquivo/"');
});

it('inclui o GoatCounter só quando configurado', function () {
    $this->artisan('build')->assertSuccessful();
    expect(built('index.html'))->not->toContain('goatcounter');

    config(['itape.site.goatcounter' => 'itapenews']);
    $this->artisan('build')->assertSuccessful();
    expect(built('index.html'))->toContain('https://itapenews.goatcounter.com/count');
});

it('não esconde a página quando não há dados', function () {
    config(['itape.paths.data' => $this->tmp.'/vazio']);

    $this->artisan('build')->assertSuccessful();

    expect(built('index.html'))->toContain('Nenhuma notícia coletada ainda.');
});

/**
 * Notícia de sessão da Câmara já escrita (como o comando `camara` grava).
 */
function sessionNewsFile(string $writtenBy = 'gemini:gemini-3.8-flash'): void
{
    $story = [
        'titulo' => 'Câmara aprova urgência para regras de eventos',
        'linha_fina' => 'Na sessão de 24 de outubro, a Câmara aprovou a urgência de dois projetos e 11 pedidos.',
        'lide' => 'A Câmara de Itapetininga aprovou no sábado (24) a urgência de dois projetos da Prefeitura.',
        'blocos' => [['intertitulo' => 'Estradas rurais', 'paragrafos' => ['O vereador Miguel Arcanjo pediu manutenção das estradas do Viracopos.']]],
        'fechamento' => '18 dos 19 vereadores estiveram presentes.',
        'omitidos' => [['documento' => 'Correspondências Recebidas Nº 340/2026', 'motivo' => 'Denúncia anônima.']],
    ];
    $list = [
        'titulo' => 'Vereadores fazem 21 pedidos na sessão de 24 de outubro',
        'linha_fina' => 'Resumo da 53ª Sessão Ordinária de 2026.',
        'resumo' => ['A Câmara aprovou a urgência de dois projetos da Prefeitura.'],
        'secoes' => [['tema' => 'Ruas e estradas', 'itens' => ['Manutenção das estradas rurais do Viracopos (Miguel Arcanjo).']]],
        'tambem' => ['Presença: 18 vereadores.'],
        'omitidos' => [],
    ];
    $gemini = str_starts_with($writtenBy, 'gemini');

    JsonFile::write(config('itape.paths.data').'/camara/noticias/1608.json', [
        'session' => 1608,
        'title' => '53ª Sessão Ordinária de 2026',
        'date' => '2026-10-24',
        'closed_at' => '20:13',
        'url' => 'https://itapetininga.siscam.com.br/Sessoes/Sessao/1608',
        'written_by' => $writtenBy,
        'written_at' => '2026-10-25T08:00:00-03:00',
        'format' => $gemini ? 'texto' : 'lista',
        'article' => $gemini ? $story : $list,
    ]);
}

/**
 * Dados da sessão como o coletor grava (para a lista completa de documentos).
 */
function sessionDataFile(): void
{
    $parser = new SiscamParser('https://itapetininga.siscam.com.br');
    $session = $parser->session(1608, fixtureContents('siscam-sessao.html'));
    $session->agenda = $parser->agenda(fixtureContents('siscam-roteiro.html'));
    (new SessionRepository(config('itape.paths.data').'/camara/sessoes'))->save($session);
}

it('publica a notícia da sessão da Câmara numa página própria', function () {
    sessionNewsFile();

    $this->artisan('build')->assertSuccessful();
    $page = built('camara/53a-sessao-ordinaria-de-2026/index.html');

    expect($page)->toContain('<h1 class="page-title">Câmara aprova urgência para regras de eventos</h1>')
        ->toContain('Sessão da Câmara · sábado, 24 de outubro de 2026')
        ->toContain('<p class="session-lede">A Câmara de Itapetininga aprovou no sábado (24) a urgência de dois projetos da Prefeitura.</p>')
        ->toContain('<h2>Estradas rurais</h2>')
        ->toContain('<p>O vereador Miguel Arcanjo pediu manutenção das estradas do Viracopos.</p>')
        ->toContain('<p>18 dos 19 vereadores estiveram presentes.</p>')
        ->toContain('<div class="session-cover is-large">')
        ->toContain('<span class="session-cover-number">53ª</span>')
        ->toContain('<span class="session-cover-kind">Sessão Ordinária</span>')
        ->toContain('<img src="/assets/camara.avif"')
        ->toContain('Plenário da Câmara Municipal de Itapetininga')
        ->toContain('O texto a seguir foi gerado automaticamente com inteligência artificial a partir dos documentos oficiais e pode conter erros.')
        ->toContain('href="https://itapetininga.siscam.com.br/Sessoes/Sessao/1608"')
        // O que o modelo deixou de fora por ser sensível não vai para a página.
        ->not->toContain('Denúncia anônima')
        // O aviso de que o texto é automático vem antes do texto.
        ->and(strpos($page, '<p class="session-note">'))->toBeLessThan(strpos($page, '<p class="session-lede">'));
});

it('avisa quando a notícia da sessão foi montada sem IA', function () {
    sessionNewsFile('modelo');

    $this->artisan('build')->assertSuccessful();

    expect(built('camara/53a-sessao-ordinaria-de-2026/index.html'))
        ->toContain('O texto a seguir foi montado automaticamente a partir dos documentos oficiais e pode conter erros.')
        ->toContain('<h2 id="em-resumo">Em resumo</h2>')
        ->toContain('<li>Manutenção das estradas rurais do Viracopos (Miguel Arcanjo).</li>')
        ->not->toContain('inteligência artificial')
        ->not->toContain('class="session-lede"');
});

it('lista abaixo da notícia todos os documentos e votações da sessão, com o link oficial', function () {
    sessionNewsFile();
    sessionDataFile();

    $this->artisan('build')->assertSuccessful();
    $docs = Str::betweenFirst(built('camara/53a-sessao-ordinaria-de-2026/index.html'), '<details class="session-docs">', '</details>');

    expect(built('camara/53a-sessao-ordinaria-de-2026/index.html'))
        ->toContain('<span class="session-cover-stats">21 pedidos · 2 urgências aprovadas · 18 de 19 vereadores · 1h13 de sessão</span>')
        ->and($docs)->toContain('Todos os documentos da sessão (26)')
        ->toContain('<h3>Votações</h3>')
        ->toContain('href="https://itapetininga.siscam.com.br/Documentos/Documento/122768" target="_blank" rel="noopener">Projeto de Lei Complementar Nº 14/2026 - Executivo</a>')
        ->toContain('Aprovado · socilitando regime de urgência · a favor: 17')
        ->toContain('<h3>Indicações</h3>')
        ->toContain('href="https://itapetininga.siscam.com.br/Documentos/Documento/122889" target="_blank" rel="noopener">Requerimento Nº 701/2026</a>')
        // Nomes curtos também na lista oficial.
        ->toContain('<span class="session-docs-author">(Itamar Martins)</span>')
        // Correspondências e respostas ficam de fora da lista.
        ->not->toContain('anônimo')
        ->not->toContain('Resposta Nº 1');
});

it('publica a notícia mesmo sem os dados da sessão, sem a lista de documentos', function () {
    sessionNewsFile();

    $this->artisan('build')->assertSuccessful();

    expect(built('camara/53a-sessao-ordinaria-de-2026/index.html'))->not->toContain('session-docs"');
});

it('põe a notícia da sessão na linha do tempo, no tema Política, abrindo na mesma aba', function () {
    sessionNewsFile();

    $this->artisan('build')->assertSuccessful();
    $link = 'href="http://localhost:8000/camara/53a-sessao-ordinaria-de-2026/"';
    // O card inteiro que contém o link (a foto vem antes do chapéu).
    $card = function (string $html) use ($link): string {
        $at = (int) strpos($html, $link);
        $start = (int) strrpos(substr($html, 0, $at), '<article');

        return substr($html, $start, (int) strpos($html, '</article>', $at) - $start);
    };

    expect(built('index.html'))->toContain($link.'>Câmara aprova urgência para regras de eventos</a>')
        ->and($card(built('index.html')))->toContain('<span class="kicker-source">Sessão da Câmara</span>')
        ->toContain('href="/tema/politica/">Política</a>')
        ->toContain('<img src="http://localhost:8000/assets/camara.avif"')
        ->toContain('<span class="session-cover-number">53ª</span>')
        ->not->toContain('session-cover-stats')
        ->not->toContain('class="official"')
        ->and(built('tema/politica/index.html'))->toContain($link)
        ->and(built('feed.xml'))->toContain('http://localhost:8000/camara/53a-sessao-ordinaria-de-2026/')
        // Não é uma fonte coletada: não aparece na página de fontes.
        ->and(built('fontes/index.html'))->not->toContain('Sessões da Câmara')
        ->and(config('itape.paths.build').'/assets/camara.avif')->toBeFile();
});

it('publica a notícia do episódio com vídeo, momentos e card', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 18:00', 'America/Sao_Paulo'));
    $dir = config('itape.paths.data').'/programas/telescopio';
    $episode = [
        'program' => 'telescopio',
        'video' => 'FTQML3B-wI4',
        'title' => 'Telescópio - 03/11/2026 - Direito dos Animais',
        'topic' => 'Direito dos Animais',
        'date' => '2026-11-03',
        'seconds' => 5436,
    ];
    JsonFile::write($dir.'/FTQML3B-wI4.json', [
        ...$episode,
        'written_by' => 'gemini:gemini-3.5-flash-lite',
        'article' => VideoStory::check(videoArticle(), 5436, CarbonImmutable::parse('2026-11-03', 'America/Sao_Paulo')),
    ]);
    // Episódio ainda sem notícia: não aparece.
    JsonFile::write($dir.'/Lf_ZIAQo0yM.json', [...$episode, 'video' => 'Lf_ZIAQo0yM', 'date' => '2026-11-04', 'topic' => 'Geopolítica', 'attempts' => 1]);

    $this->artisan('build')->assertSuccessful();
    $page = built('programas/telescopio/2026-11-03-direito-dos-animais/index.html');

    expect($page)->toContain('Protetores de animais pedem castração gratuita em Itapetininga')
        ->toContain('TV Itapê · Telescópio · terça, 3 de novembro de 2026')
        ->toContain('youtube-nocookie.com/embed/FTQML3B-wI4')
        ->toContain('https://www.youtube.com/watch?v=FTQML3B-wI4&amp;t=3900s')
        ->toContain('gerado automaticamente com inteligência artificial a partir do vídeo')
        ->toContain('Apresentador · Ana Souza (protetora de animais)')
        ->and(built('index.html'))->toContain('TV Itapê · Telescópio')
        ->toContain('/programas/telescopio/2026-11-03-direito-dos-animais/')
        ->toContain('https://i.ytimg.com/vi/FTQML3B-wI4/hqdefault.jpg')
        ->and(is_dir(config('itape.paths.build').'/programas/telescopio/2026-11-04-geopolitica'))->toBeFalse();
});

it('usa só a data no endereço de programas sem tema e o id do vídeo na segunda parte do dia', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 18:00', 'America/Sao_Paulo'));
    $article = VideoStory::check(videoArticle(), 5436, CarbonImmutable::parse('2026-11-03', 'America/Sao_Paulo'));

    foreach (['AAA111' => 'MANHÃ DA POP  -  03/11/2026', 'BBB222' => 'MANHÃ DA POP  -  03/11/2026 ll'] as $video => $title) {
        JsonFile::write(config('itape.paths.data')."/programas/manha-da-pop/{$video}.json", [
            'program' => 'manha-da-pop', 'video' => $video, 'title' => $title, 'topic' => '',
            'date' => '2026-11-03', 'seconds' => 5436, 'article' => $article,
        ]);
    }

    $this->artisan('build')->assertSuccessful();

    expect(built('programas/manha-da-pop/2026-11-03/index.html'))->toContain('Pop FM · Manhã da Pop')
        ->and(built('programas/manha-da-pop/2026-11-03-bbb222/index.html'))->toContain('youtube-nocookie.com/embed/BBB222');
});

it('gera a página do cinema e o bloco "Hoje no cinema" na Agenda', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 18:00', 'America/Sao_Paulo'));
    $this->artisan('collect', ['--force-weather' => true])->assertSuccessful();
    $this->artisan('build')->assertSuccessful();

    $page = built('cinema/index.html');
    $agenda = built('agenda/index.html');

    expect(JsonFile::read(config('itape.paths.data').'/cinema.json')['days'])->toHaveCount(7)
        ->and($page)->toContain('Os Farofeiros 3')
        ->toContain('Hoje, segunda, 9 de novembro de 2026')
        ->toContain('Sala 1 · Nacional · 2D')
        // 14:50 e 17:05 já começaram às 18h.
        ->toContain('class="cinema-time is-past"')
        ->toContain('https://www.grupocine.com.br/venda/')
        ->and($agenda)->toContain('Hoje no cinema')
        ->toContain('/cinema/">Programação completa')
        ->toContain('data-slides')
        ->toContain('https://static3.moviehub.com.br/fotos/filmes/poster/16672_medio.jpg')
        // Sem link no menu: a página do cinema é aberta pelo bloco da Agenda.
        ->and(built('index.html'))->not->toContain('/cinema/"');
});

it('avisa quando não há programação do cinema', function () {
    // A coleta do beforeEach já trouxe a programação: aqui o cinema falhou desde sempre.
    unlink(config('itape.paths.data').'/cinema.json');
    $this->artisan('build')->assertSuccessful();

    expect(built('cinema/index.html'))->toContain('Programação indisponível no momento')
        ->and(built('agenda/index.html'))->not->toContain('no cinema</h2>');
});
