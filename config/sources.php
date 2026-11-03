<?php

/*
|--------------------------------------------------------------------------
| Fontes de notícias
|--------------------------------------------------------------------------
|
| Cada fonte usa um adaptador:
|   - rss             feed RSS/Atom (SimplePie)
|   - html_list       página HTML com links de notícias (link_pattern)
|   - sitemap_stream  primeiros bytes de um sitemap (max_bytes)
|
| filter  => true  só aceita itens que mencionam "Itapetininga".
| ignore  => regex removidas do resumo e do texto usado no filtro (textos
|            fixos do site, como chamadas para canais de WhatsApp).
| caption_lines => linhas de legenda/crédito após a foto inicial da descrição.
| page    => seletores CSS usados ao abrir a página da notícia para completar
|            título, resumo, imagem e data. Sem seletor, usa as meta tags og:*.
|
*/

return [

    'g1' => [
        'name' => 'g1',
        'color' => '#c4170c',
        'type' => 'rss',
        'url' => 'https://g1.globo.com/rss/g1/sp/itapetininga-regiao/',
        'homepage' => 'https://g1.globo.com/sp/itapetininga-regiao/',
        'filter' => true,
        'ignore' => ['/(📲\s*)?Participe do canal do g1 [^.]{1,60}? no WhatsApp/iu', '/g1 Itapetininga e Regi(ã|a)o/iu'],
        // A descrição começa com a foto, uma linha de legenda e uma de crédito.
        'caption_lines' => 2,
    ],

    'jornal' => [
        'name' => 'Jornal de Itapetininga',
        'short' => 'Jornal',
        'color' => '#1f5fa8',
        'type' => 'rss',
        'url' => 'https://www.jornaldeitapetininga.com.br/feed',
        'homepage' => 'https://www.jornaldeitapetininga.com.br/',
    ],

    'correio' => [
        'name' => 'Correio de Itapetininga',
        'short' => 'Correio',
        'color' => '#d97706',
        'type' => 'sitemap_stream',
        'url' => 'https://correiodeitapetininga.com.br/sitemap.xml',
        'homepage' => 'https://correiodeitapetininga.com.br/',
        'link_pattern' => '#/noticia/\d+/#',
        // As notícias mais novas ficam no topo; 40 KB trazem ~140 URLs.
        'max_bytes' => 40_960,
        'limit' => 25,
        // O sitemap marca horários locais com "Z".
        'naive_timezone' => true,
    ],

    'prefeitura' => [
        'name' => 'Prefeitura de Itapetininga',
        'short' => 'Prefeitura',
        'color' => '#1c5454',
        'type' => 'html_list',
        'url' => 'https://www.itapetininga.sp.gov.br/',
        'homepage' => 'https://www.itapetininga.sp.gov.br/',
        'official' => true,
        'link_pattern' => '#/noticia/\d+/[^/]+/?$#',
        'limit' => 20,
        'page' => [
            'summary' => '.noticia-interna-conteudo p',
            'date' => 'h2 span',
        ],
    ],

    'folha' => [
        'name' => 'Folha de Itapetininga',
        'short' => 'Folha',
        'color' => '#0f766e',
        'type' => 'html_list',
        'url' => 'https://www.folhadeitapetininga.com.br/',
        'homepage' => 'https://www.folhadeitapetininga.com.br/',
        'link_pattern' => '#/noticias/default\.asp\?id=\d+$#',
        'limit' => 15,
        'page' => [
            'title' => 'h1.card-title',
            'summary' => '.card-box p.text-black',
        ],
    ],

    'tvsmais' => [
        'name' => 'TVS+ (SBT)',
        'short' => 'TVS+',
        'color' => '#7c3aed',
        'type' => 'rss',
        'url' => 'https://www.tvsmais.com.br/?s=itapetininga&feed=rss2',
        'homepage' => 'https://www.tvsmais.com.br/',
        // A busca do WordPress também traz notícias de outras cidades.
        'filter' => true,
    ],

    'camara' => [
        'name' => 'Câmara Municipal',
        'short' => 'Câmara',
        'color' => '#475569',
        'type' => 'rss',
        'url' => 'http://www.camaraitapetininga.sp.gov.br/noticias?format=feed&type=rss',
        'homepage' => 'http://www.camaraitapetininga.sp.gov.br/noticias',
        'official' => true,
    ],

    'xeh' => [
        'name' => 'Xeh',
        'color' => '#db2777',
        'type' => 'rss',
        'url' => 'https://xeh.com.br/feed/',
        'homepage' => 'https://xeh.com.br/',
        'filter' => true,
    ],

    'panoramico' => [
        'name' => 'Jornal Panorâmico',
        'short' => 'Panorâmico',
        'color' => '#0369a1',
        'type' => 'rss',
        'url' => 'https://jornalpanoramico.com.br/feed/',
        'homepage' => 'https://jornalpanoramico.com.br/',
        // Publica também notícias de Sorocaba e nacionais.
        'filter' => true,
    ],

    'ola' => [
        'name' => 'Olá Itapetininga',
        'short' => 'Olá',
        'color' => '#ea580c',
        'type' => 'rss',
        'url' => 'https://olaitapetininga.com.br/feed',
        'homepage' => 'https://olaitapetininga.com.br/',
        // Mistura agenda de outras cidades (Sorocaba, São Paulo).
        'filter' => true,
    ],

];
