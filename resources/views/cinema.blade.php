@extends('layouts.app', ['currentPage' => 'cinema'])

@section('title', 'Cinema em Itapetininga · '.$site['name'])
@section('description', 'Filmes em cartaz e horários das sessões no '.$cinema['name'].' nos próximos dias.')
@section('path', '/cinema/')

@section('content')
  <h1 class="page-title">Cinema</h1>
  <p class="page-lead">Filmes em cartaz e horários no {{ $cinema['name'] }}. Ingressos e detalhes no <a href="{{ $cinema['url'] }}" target="_blank" rel="noopener">site do cinema</a>.</p>

  @if ($days)
    {{-- Atalhos para cada dia. --}}
    <nav class="cinema-days" aria-label="Dias">
      @foreach ($days as $day)
        <a href="#dia-{{ $day['date']->format('Y-m-d') }}">{{ $day['date']->isSameDay($now) ? 'Hoje' : ($day['date']->isSameDay($now->addDay()) ? 'Amanhã' : \App\Site\Timeline::weekdayShort($day['date']).' '.$day['date']->format('j')) }}</a>
      @endforeach
    </nav>

    @foreach ($days as $day)
      <section class="cinema-day" id="dia-{{ $day['date']->format('Y-m-d') }}" aria-labelledby="titulo-{{ $day['date']->format('Y-m-d') }}">
        <h2 class="cinema-day-title" id="titulo-{{ $day['date']->format('Y-m-d') }}">
          {{ $day['date']->isSameDay($now) ? 'Hoje, ' : ($day['date']->isSameDay($now->addDay()) ? 'Amanhã, ' : '') }}{{ \App\Site\Timeline::longDate($day['date']) }}
        </h2>

        @foreach ($day['films'] as $film)
          <article class="cinema-film{{ $film['poster'] ? '' : ' noimg' }}">
            @if ($film['poster'])
              <img class="cinema-poster" src="{{ $film['poster'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('.cinema-film').classList.add('noimg'); this.remove()">
            @endif
            <div class="cinema-film-body">
              <h3>
                @if ($film['url'])<a href="{{ $film['url'] }}" target="_blank" rel="noopener">{{ $film['title'] }}</a>@else{{ $film['title'] }}@endif
              </h3>
              <p class="cinema-meta">
                @if ($film['tag'] !== '')<span class="cinema-tag">{{ $film['tag'] }}</span>@endif
                {{ implode(' · ', array_filter([$film['rating'] !== '' ? ($film['rating'] === 'L' ? 'Livre' : $film['rating'].' anos') : null, $film['genre'] ?: null, $film['minutes'] ? $film['minutes'].' min' : null])) }}
              </p>
              @foreach ($film['sessions'] as $session)
                <div class="cinema-session">
                  <span class="cinema-session-info">{{ $session['info'] }}</span>
                  <span class="cinema-times">
                    @foreach ($session['times'] as $time)
                      <a class="cinema-time{{ $time['past'] ? ' is-past' : '' }}" href="{{ $time['url'] }}" target="_blank" rel="noopener">{{ $time['time'] }}<span class="visually-hidden">: comprar ingresso para {{ $film['title'] }}{{ $time['past'] ? ' (sessão já começou)' : '' }}</span></a>
                    @endforeach
                  </span>
                </div>
              @endforeach
            </div>
          </article>
        @endforeach
      </section>
    @endforeach

    <p class="cinema-source">Programação copiada do site do {{ $cinema['name'] }}, que pode mudar sem aviso: confira no <a href="{{ $cinema['url'] }}" target="_blank" rel="noopener">site do cinema</a> antes de sair de casa.</p>
  @else
    <p class="empty">Programação indisponível no momento. Veja no <a href="{{ $cinema['url'] }}" target="_blank" rel="noopener">site do cinema</a>.</p>
  @endif
@endsection
