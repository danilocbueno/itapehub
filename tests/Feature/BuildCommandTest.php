<?php

use App\Data\Item;
use App\Pipeline\Clusterer;
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

it('lista os dias do arquivo agrupados por mês', function () {
    $this->artisan('build')->assertSuccessful();

    expect(built('arquivo/index.html'))
        ->toContain('Outubro de 2026')
        ->toContain('href="/arquivo/2026/10/25/"');
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
