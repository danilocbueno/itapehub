@extends('layouts.app')

@section('top')
  @include('partials.alerts')
  {{-- Linha de data com a previsão, logo acima do nome. --}}
  <div class="dateline">
    <span>{{ \App\Site\Timeline::dateline($now) }}</span>
    @include('partials.weather')
  </div>
@endsection

@section('content')
  @forelse ($days as $day => $clusters)
    <section class="day">
      {{-- O primeiro dia abre com a capa, sem separador; os seguintes têm título. --}}
      @unless ($loop->first)
        <h2 data-day="{{ $day }}">{{ \App\Site\Timeline::dayLabel($day, $now) }}</h2>
      @endunless
      @if ($loop->first)
        {{-- Capa: manchete e as duas notícias seguintes ao lado (lado a lado só no desktop) --}}
        <div class="front">
          @foreach (array_slice($clusters, 0, 3) as $cluster)
            @include('partials.card', ['lead' => $loop->first, 'side' => ! $loop->first])
          @endforeach
        </div><!-- /front -->
        @if ($events)
          <section class="upcoming" aria-label="Próximos eventos">
            <h2>Próximos eventos</h2>
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
            <a class="upcoming-more" href="{{ $site['base'] }}/agenda/">Ver agenda completa</a>
          </section>
        @endif
        @php($clusters = array_slice($clusters, 3))
      @endif
      <div class="cards">
        @foreach ($clusters as $cluster)
          @include('partials.card', ['lead' => false])
        @endforeach
      </div>
    </section>
  @empty
    <p class="empty">Nenhuma notícia coletada ainda.</p>
  @endforelse

  <p class="more"><a href="{{ $site['base'] }}/arquivo/">Ver notícias anteriores</a></p>
@endsection
