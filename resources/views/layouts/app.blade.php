@php($base = $site['base'])
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', $site['name'].' · '.$site['tagline'])</title>
  <meta name="description" content="@yield('description', $site['tagline'])">
  <meta name="theme-color" content="#b42318">
  <link rel="canonical" href="{{ $site['url'] }}@yield('path', '/')">
  <link rel="alternate" type="application/rss+xml" title="{{ $site['name'] }}" href="{{ $base }}/feed.xml">
  {{-- Favicon: o terminal da linha do logo (uma parada e o anel). --}}
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23f3eee4'/%3E%3Cpath d='M0 50H52' stroke='%23b42318' stroke-width='11'/%3E%3Ccircle cx='22' cy='50' r='9' fill='%231a1915'/%3E%3Ccircle cx='64' cy='50' r='18' fill='%23f3eee4' stroke='%23b42318' stroke-width='11'/%3E%3C/svg%3E">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:site_name" content="{{ $site['name'] }}">
  <meta property="og:title" content="@yield('title', $site['name'])">
  <meta property="og:description" content="@yield('description', $site['tagline'])">
  <meta property="og:url" content="{{ $site['url'] }}@yield('path', '/')">
  <link rel="preload" href="{{ $base }}/assets/fonts/newsreader.woff2" as="font" type="font/woff2" crossorigin>
  <style>
    @font-face { font-family: "Newsreader"; src: url("{{ $base }}/assets/fonts/newsreader.woff2") format("woff2"); font-weight: 200 800; font-style: normal; font-display: swap; }
    @font-face { font-family: "Newsreader"; src: url("{{ $base }}/assets/fonts/newsreader-italic.woff2") format("woff2"); font-weight: 200 800; font-style: italic; font-display: swap; }
    @font-face { font-family: "IBM Plex Sans"; src: url("{{ $base }}/assets/fonts/ibm-plex-sans.woff2") format("woff2"); font-weight: 100 700; font-style: normal; font-display: swap; }
  </style>
  <style>{!! $css !!}</style>
  @if ($site['goatcounter'])
    <script data-goatcounter="https://{{ $site['goatcounter'] }}.goatcounter.com/count" async src="https://gc.zgo.at/count.js"></script>
  @endif
</head>
<body>
  @hasSection('top')
    <div class="page-top">@yield('top')</div>
  @endif
  <header class="masthead">
    <div class="masthead-inner">
      <div class="masthead-title">
        <div class="masthead-brand">
          @include('partials.brand')
          <p class="tagline">{{ $site['tagline'] }}</p>
        </div>
        {{-- Data e previsão ao lado do nome (só na home). --}}
        @yield('masthead-sides')
      </div>
      <nav class="topics" aria-label="Assuntos">
        <a class="topics-agenda" href="{{ $base }}/agenda/"@if (($currentPage ?? null) === 'agenda') aria-current="page"@endif>Agenda</a>
        @foreach ($themes as $slug => $theme)
          <a href="{{ $base }}/tema/{{ $slug }}/"@if (($currentTheme ?? null) === $slug) aria-current="page"@endif>{{ $theme['name'] }}</a>
        @endforeach
        {{-- Fecha o menu em vermelho, como a Agenda abre; no celular, vira uma lupa fixa à direita. Sem JavaScript, leva à página de busca; com ele, abre o modal (site.js). --}}
        <a class="topics-search" href="{{ $base }}/busca/" aria-haspopup="dialog" aria-controls="search-dialog" data-search-toggle @if (($currentPage ?? null) === 'busca') aria-current="page"@endif>@include('partials.search-icon')<span class="topics-search-label">Busca</span></a>
      </nav>
    </div>
  </header>

  <dialog id="search-dialog" class="search-dialog" aria-labelledby="search-dialog-title" data-search-dialog>
    <form class="search-panel" action="{{ $base }}/busca/" method="get" role="search" data-search-panel data-empty>
      <h2 id="search-dialog-title" class="sr-only">Buscar no {{ $site['name'] }}</h2>
      <div class="search-field">
        @include('partials.search-icon')
        <label class="sr-only" for="search-dialog-input">Buscar notícias</label>
        <input id="search-dialog-input" name="q" type="search" placeholder="Buscar notícias, fontes, assuntos…" autocomplete="off" enterkeyhint="search" data-search-input>
        <button type="button" class="search-close" aria-label="Fechar a busca" data-search-close>
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </button>
      </div>
      <p class="search-summary" aria-live="polite" data-search-summary></p>
      <div class="search-results" data-search-results data-limit="7"></div>
      <div class="search-foot" aria-hidden="true">
        <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span>
        <span><kbd>↵</kbd> abrir</span>
        <span><kbd data-search-shortcut>Ctrl K</kbd> abrir e fechar</span>
        <span class="search-foot-all">Enter mostra todos os resultados</span>
      </div>
    </form>
  </dialog>

  <main class="wrap">
    @yield('content')
  </main>

  <footer class="foot">
    <div class="foot-inner">
      <nav>
        <a href="{{ $base }}/">Início</a>
        <a href="{{ $base }}/hoje/">Resumo do dia</a>
        <a href="{{ $base }}/agenda/">Agenda</a>
        <a href="{{ $base }}/arquivo/">Arquivo</a>
        <a href="{{ $base }}/fontes/">Fontes</a>
        <a href="{{ $base }}/feed.xml">RSS</a>
      </nav>
      @if ($problems)
        <p class="warn">Algumas fontes estão com problema na coleta: {{ implode(', ', $problems) }}. <a href="{{ $base }}/fontes/">Ver detalhes</a>.</p>
      @endif
      <p>{{ $site['name'] }} reúne títulos e resumos publicados pelos veículos de Itapetininga. Cada notícia leva ao site original, que é o dono do conteúdo.</p>
    </div>
  </footer>

  <script>window.ITAPE_BASE = @json($base);</script>
  <script>{!! $js !!}</script>
</body>
</html>
