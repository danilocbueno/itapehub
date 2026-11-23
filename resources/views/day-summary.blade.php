@extends('layouts.app')

@section('title', $item->title.' · '.$site['name'])
@section('description', $item->summary)
@section('path', '/'.$path)

@section('content')
  <article class="session-news day-summary">
    <p class="session-kicker">Resumo do dia · {{ \App\Site\Timeline::longDate($date) }}</p>
    <h1 class="page-title">{{ $item->title }}</h1>
    <p class="page-lead">{{ $item->summary }}</p>

    @if ($summary['kind'] === 'ia')
      <p class="session-note">
        Texto gerado automaticamente com inteligência artificial a partir dos títulos e resumos das notícias do dia, e pode conter erros.
        Abaixo de cada parte estão as notícias de onde ela saiu: confira no site de cada veículo.
      </p>
    @else
      <p class="session-note">As notícias do dia reunidas pelo {{ $site['name'] }}, das publicadas por mais veículos para as demais. Cada uma leva ao site original.</p>
    @endif

    @include('partials.day-summary-body')

    <p class="more"><a href="{{ $site['base'] }}/arquivo/{{ $date->format('Y/m/d') }}/">Todas as notícias de {{ $date->format('d/m') }}</a></p>
  </article>
@endsection
