@extends('layouts.app')

@section('top')
  @include('partials.alerts')
@endsection

@section('masthead-sides')
  <span class="masthead-date">{{ \App\Site\Timeline::dateline($now) }}</span>
  <div class="masthead-weather">@include('partials.weather')</div>
@endsection

@section('content')
  @if ($front)
    <section class="day">
      {{-- Capa: as três notícias mais recentes, mesmo que venham de dias diferentes (lado a lado só no desktop) --}}
      <div class="front">
        @foreach ($front as $cluster)
          @include('partials.card', ['lead' => $loop->first, 'side' => ! $loop->first])
        @endforeach
      </div><!-- /front -->
      @if ($events)
        <section class="upcoming" aria-label="Próximos eventos">
          <div class="upcoming-head">
            <h2>Próximos eventos</h2>
            <a class="upcoming-more" href="{{ $site['base'] }}/agenda/">Ver agenda completa</a>
          </div>
          @foreach ($events as $event)
            @php($item = $event->cluster->primary)
            @php($image = $event->cluster->image())
            <a class="upcoming-item{{ $image ? '' : ' noimg' }}" href="{{ $item->url }}" target="_blank" rel="noopener">
              @if ($image)
                <span class="upcoming-thumb"><img src="{{ $image }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.upcoming-item').classList.add('noimg'); this.parentNode.remove()"></span>
              @endif
              <span class="upcoming-text">
                <span class="upcoming-when">{{ \App\Site\Timeline::agendaDay($event->at->format('Y-m-d'), $now) }}@if ($event->hasTime) · {{ $event->at->format($event->at->minute ? 'G\hi' : 'G\h') }}@endif</span>
                <span class="upcoming-title">{{ $item->title }}</span>
              </span>
            </a>
          @endforeach
        </section>
      @endif
    </section>
  @else
    <p class="empty">Nenhuma notícia coletada ainda.</p>
  @endif

  @foreach ($days as $day => $clusters)
    <section class="day">
      {{-- O dia da manchete continua a capa sem separador; depois dos eventos, ou nos outros dias, vem o nome do dia. --}}
      @unless ($day === $frontDay && ! $events)
        <h2 data-day="{{ $day }}">{{ \App\Site\Timeline::dayLabel($day, $now) }}</h2>
      @endunless
      <div class="cards">
        @foreach ($clusters as $cluster)
          @include('partials.card', ['lead' => false])
        @endforeach
      </div>
    </section>
  @endforeach

  <p class="more"><a href="{{ $site['base'] }}/arquivo/">Ver notícias anteriores</a></p>
@endsection
