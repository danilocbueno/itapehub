@extends('layouts.app')

@php($article = $news['article'])
@section('title', $article['titulo'].' · '.$site['name'])
@section('description', $article['linha_fina'])
@section('path', '/camara/'.$slug.'/')

@section('content')
  <article class="session-news">
    <p class="session-kicker">Sessão da Câmara · {{ \App\Site\Timeline::longDate($date) }}</p>
    <h1 class="page-title">{{ $article['titulo'] }}</h1>
    <p class="page-lead">{{ $article['linha_fina'] }}</p>
    <figure class="session-photo">
      <img src="{{ $site['base'] }}/{{ \App\Camara\SessionNews::IMAGE }}" alt="Plenário da Câmara Municipal de Itapetininga" width="2048" height="1152">
    </figure>

    @if (isset($article['lide']))
      {{-- Texto corrido (Gemini): lide, blocos e fechamento. --}}
      <div class="session-story">
        <p class="session-lede">{{ $article['lide'] }}</p>
        @foreach ($article['blocos'] as $block)
          @if ($block['intertitulo'] !== '')
            <h2>{{ $block['intertitulo'] }}</h2>
          @endif
          @foreach ($block['paragrafos'] as $paragraph)
            <p>{{ $paragraph }}</p>
          @endforeach
        @endforeach
        @if ($article['fechamento'] !== '')
          <p>{{ $article['fechamento'] }}</p>
        @endif
      </div>
    @else
      {{-- Lista (modelo fixo, sem IA). --}}
      <section aria-labelledby="em-resumo">
        <h2 id="em-resumo">Em resumo</h2>
        <ul>
          @foreach ($article['resumo'] as $point)
            <li>{{ $point }}</li>
          @endforeach
        </ul>
      </section>

      @foreach ($article['secoes'] as $section)
        <section>
          <h2>{{ $section['tema'] }}</h2>
          <ul>
            @foreach ($section['itens'] as $line)
              <li>{{ $line }}</li>
            @endforeach
          </ul>
        </section>
      @endforeach

      @if ($article['tambem'])
        <section aria-labelledby="tambem">
          <h2 id="tambem">Também na sessão</h2>
          <ul>
            @foreach ($article['tambem'] as $line)
              <li>{{ $line }}</li>
            @endforeach
          </ul>
        </section>
      @endif
    @endif

    @if ($session)
      {{-- Os documentos oficiais, completos, direto do SisCam. --}}
      @php($documents = array_values(array_filter($session->agenda, fn ($s) => ! preg_match('~^(Correspond|Respostas)~u', $s['section']))))
      <details class="session-docs">
        <summary>Todos os documentos da sessão ({{ array_sum(array_map(fn ($s) => count($s['items']), $documents)) }})</summary>
        @if ($session->votes)
          <h3>Votações</h3>
          <ul>
            @foreach ($session->votes as $vote)
              @php($outcome = implode(' · ', array_filter([$vote['result'], mb_strtolower($vote['phase']), implode(', ', array_map(fn ($t) => mb_strtolower($t['label']).': '.$t['count'], $vote['tallies']))])))
              <li>
                @if ($vote['url'])<a href="{{ $vote['url'] }}" target="_blank" rel="noopener">{{ $vote['title'] }}</a>@else{{ $vote['title'] }}@endif
                @if ($vote['author'] !== '')<span class="session-docs-author">({{ $vote['author'] }})</span>@endif:
                {{ $outcome }}
              </li>
            @endforeach
          </ul>
        @endif
        @foreach ($documents as $section)
          <h3>{{ $section['section'] }}</h3>
          <ul>
            @foreach ($section['items'] as $doc)
              <li>
                @if ($doc['url'])<a href="{{ $doc['url'] }}" target="_blank" rel="noopener">{{ $doc['title'] }}</a>@else{{ $doc['title'] }}@endif
                @if ($doc['author'] !== '')<span class="session-docs-author">({{ $doc['author'] }})</span>@endif:
                {{ $doc['text'] }}
              </li>
            @endforeach
          </ul>
        @endforeach
      </details>
    @endif

    <p class="session-note">
      @if (str_starts_with($news['written_by'], 'gemini'))
        Notícia escrita com inteligência artificial (Gemini) a partir dos documentos oficiais da {{ $news['title'] }}.
      @else
        Resumo montado automaticamente a partir dos documentos oficiais da {{ $news['title'] }}.
      @endif
      Confira a sessão completa no <a href="{{ $news['url'] }}" target="_blank" rel="noopener">sistema da Câmara (SisCam)</a>.
    </p>
  </article>
@endsection
