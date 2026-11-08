@extends('layouts.app')

@php($article = $episode['article'])
@php($watch = 'https://www.youtube.com/watch?v='.$episode['video'])
@section('title', $article['titulo'].' · '.$site['name'])
@section('description', $article['linha_fina'])
@section('path', '/programas/'.$program->key.'/'.$slug.'/')

@section('content')
  <article class="session-news program-news">
    <p class="session-kicker">{{ $program->station }} · {{ $program->name }} · {{ \App\Site\Timeline::longDate($date) }}</p>
    <h1 class="page-title">{{ $article['titulo'] }}</h1>
    <p class="page-lead">{{ $article['linha_fina'] }}</p>

    {{-- Vídeo do programa, pelo modo de privacidade do YouTube (sem cookies até o play). --}}
    <div class="program-video">
      <iframe src="https://www.youtube-nocookie.com/embed/{{ $episode['video'] }}" title="{{ $episode['title'] }}" loading="lazy"
        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
    </div>
    <p class="program-credit">Vídeo: programa {{ $program->name }}, da {{ $program->station }}. <a href="{{ $watch }}" target="_blank" rel="noopener">Assista no YouTube</a>.</p>

    <p class="session-note">
      O texto a seguir foi gerado automaticamente com inteligência artificial a partir do vídeo do programa e pode conter erros.
      Confira o programa completo no <a href="{{ $watch }}" target="_blank" rel="noopener">canal da {{ $program->station }}</a>.
    </p>

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

    <h2>Momentos do programa</h2>
    <ul class="program-moments">
      @foreach ($article['momentos'] as $moment)
        <li><a class="program-time" href="{{ $watch }}&amp;t={{ $moment['segundos'] }}s" target="_blank" rel="noopener">{{ $moment['inicio'] }}</a> <span>{{ $moment['assunto'] }}</span></li>
      @endforeach
    </ul>

    @if ($article['participantes'])
      <h2>Participantes</h2>
      {{-- "Ana Souza (protetora de animais) · Apresentador": sem nome seguro, só a função. --}}
      <p class="program-people">{{ implode(' · ', array_map(fn ($p) => $p['nome'] !== '' ? $p['nome'].($p['papel'] !== '' ? ' ('.$p['papel'].')' : '') : ucfirst($p['papel']), $article['participantes'])) }}</p>
    @endif
  </article>
@endsection
