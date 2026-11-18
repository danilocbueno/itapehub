@extends('layouts.app')

@section('title', 'Busca · '.$site['name'])
@section('description', 'Busque nas notícias de Itapetininga reunidas pelo '.$site['name'].'.')
@section('path', '/busca/')

@section('content')
  <h1 class="page-title">Busca</h1>
  <form class="search-page" action="{{ $site['base'] }}/busca/" method="get" role="search" data-search-page>
    <div class="search-field">
      @include('partials.search-icon')
      <label class="sr-only" for="search-page-input">Buscar notícias</label>
      <input id="search-page-input" name="q" type="search" placeholder="Buscar notícias, fontes, assuntos…" autocomplete="off" enterkeyhint="search" data-search-input>
      <button type="submit" class="search-submit">Buscar</button>
    </div>
    <p class="search-summary" aria-live="polite" data-search-summary></p>
    <div class="search-results" data-search-results></div>
  </form>
  <noscript><p class="page-lead">A busca precisa de JavaScript. Você também pode navegar pelo <a href="{{ $site['base'] }}/arquivo/">arquivo por dia</a>.</p></noscript>
  <p class="search-note">A busca procura no título e no resumo das notícias. O texto completo está no site de cada veículo.</p>
@endsection
