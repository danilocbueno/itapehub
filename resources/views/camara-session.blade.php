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

    <p class="session-note">
      @if (str_starts_with($news['written_by'], 'gemini'))
        Resumo escrito com inteligência artificial (Gemini) a partir dos documentos oficiais da {{ $news['title'] }}.
      @else
        Resumo montado automaticamente a partir dos documentos oficiais da {{ $news['title'] }}.
      @endif
      Confira os documentos completos no <a href="{{ $news['url'] }}" target="_blank" rel="noopener">sistema da Câmara (SisCam)</a>.
    </p>
  </article>
@endsection
