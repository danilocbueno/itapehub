@extends('layouts.app', ['currentPage' => 'agenda'])

@section('title', 'Agenda de Itapetininga · '.$site['name'])
@section('description', 'Eventos, shows, festas e competições em Itapetininga nos próximos dias, a partir das notícias da cidade.')
@section('path', '/agenda/')

@section('content')
  @php
    $days = config('itape.agenda.days');
  @endphp
  <h1 class="page-title">Agenda</h1>
  <p class="page-lead">Shows, festas, competições e outros eventos anunciados nas notícias da cidade. As datas são identificadas automaticamente nos textos: confira na notícia antes de sair de casa.</p>
  @if ($cinemaNext)
    {{-- Slides com os pôsteres das sessões que ainda vão começar hoje (ou do próximo dia). --}}
    <section class="cinema-strip" aria-labelledby="hoje-no-cinema">
      <div class="cinema-strip-head">
        <h2 id="hoje-no-cinema">{{ $cinemaNext['date']->isSameDay($now) ? 'Hoje' : ($cinemaNext['date']->isSameDay($now->addDay()) ? 'Amanhã' : ucfirst(\App\Site\Timeline::longDate($cinemaNext['date']))) }} no cinema</h2>
        <a href="{{ $site['base'] }}/cinema/">Programação completa</a>
        <span class="cinema-strip-nav">
          <button type="button" data-slide="-1" aria-label="Filmes anteriores" hidden>‹</button>
          <button type="button" data-slide="1" aria-label="Próximos filmes" hidden>›</button>
        </span>
      </div>
      <ul class="cinema-slides" data-slides>
        @foreach ($cinemaNext['films'] as $film)
          <li class="cinema-slide">
            <a class="cinema-slide-poster" href="{{ $film['url'] ?? $cinema['url'] }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
              @if ($film['poster'])
                <img src="{{ $film['poster'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.remove()">
              @endif
              <span class="cinema-slide-fallback">{{ $film['title'] }}</span>
              @if ($film['tag'] !== '')<span class="cinema-slide-tag">{{ $film['tag'] }}</span>@endif
            </a>
            <h3><a href="{{ $film['url'] ?? $cinema['url'] }}" target="_blank" rel="noopener">{{ $film['title'] }}</a></h3>
            <span class="cinema-times">
              @foreach ($film['times'] as $time)
                <a class="cinema-time" href="{{ $time['url'] }}" target="_blank" rel="noopener">{{ $time['time'] }}<span class="visually-hidden">: comprar ingresso para {{ $film['title'] }}</span></a>
              @endforeach
            </span>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  <p class="agenda-count">{{ count($events) }} {{ count($events) === 1 ? 'evento' : 'eventos' }} nos próximos {{ $days }} dias</p>

  @forelse ($events as $event)
    @php
      $item = $event->cluster->primary;
      $image = $event->cluster->image();
      $source = ($sources[$item->source] ?? null)?->short ?? $item->source;
      $relative = match (true) {
          $event->at->isSameDay($now) => 'Hoje',
          $event->at->isSameDay($now->addDay()) => 'Amanhã',
          default => null,
      };
    @endphp
    <article class="agenda-item{{ $image ? '' : ' noimg' }}">
      <div class="agenda-date{{ $relative ? ' is-soon' : '' }}" aria-hidden="true">
        <span class="agenda-weekday">{{ $relative ?? \App\Site\Timeline::weekdayShort($event->at) }}</span>
        <span class="agenda-day-number">{{ $event->at->format('j') }}</span>
        <span class="agenda-month">{{ \App\Site\Timeline::monthShort($event->at) }}</span>
      </div>
      @if ($image)
        <a class="agenda-thumb" href="{{ $item->url }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
          <img src="{{ $image }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.agenda-item').classList.add('noimg'); this.parentNode.remove()">
        </a>
      @endif
      <div class="agenda-body">
        <div class="kicker">
          <span class="kicker-source">{{ $source }}</span>
          <span aria-hidden="true">·</span>
          {{-- A data já está no bloco ao lado; aqui fica só o horário. --}}
          <time datetime="{{ $event->at->toIso8601String() }}"><span class="visually-hidden">{{ \App\Site\Timeline::longDate($event->at) }}, </span>{{ $event->hasTime ? $event->at->format($event->at->minute ? 'G\hi' : 'G\h') : 'horário a confirmar' }}</time>
        </div>
        <h2><a href="{{ $item->url }}" target="_blank" rel="noopener">{{ $item->title }}</a></h2>
        @if ($item->summary !== '')
          <p>{{ $item->summary }}</p>
        @endif
        <a class="agenda-link" href="{{ $item->url }}" target="_blank" rel="noopener">Ler na fonte ({{ $source }}) →</a>
      </div>
    </article>
  @empty
    <p class="empty">Nenhum evento encontrado para os próximos {{ $days }} dias.</p>
  @endforelse
@endsection
