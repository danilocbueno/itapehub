@extends('layouts.app')

@section('title', 'Arquivo de notícias · '.$site['name'])
@section('path', '/arquivo/')

@section('content')
  <h1 class="page-title">Arquivo</h1>
  <p class="page-lead">Notícias de Itapetininga por dia. Escolha um dia no calendário.</p>

  <div class="months">
    @forelse ($months as $month => $monthDays)
      <section class="month">
        <h2>{{ \App\Site\Timeline::monthLabel($month) }}</h2>
        <table class="calendar">
          <caption class="visually-hidden">{{ \App\Site\Timeline::monthLabel($month) }}: {{ array_sum($monthDays) }} notícias</caption>
          <thead>
            <tr>
              @foreach (\App\Site\Calendar::WEEKDAYS as $weekday)
                <th scope="col">{{ $weekday }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach (\App\Site\Calendar::weeks($month, $monthDays) as $week)
              <tr>
                @foreach ($week as $cell)
                  @if ($cell === null)
                    <td class="cal-out"></td>
                  @elseif ($cell['count'] > 0)
                    <td class="cal-day{{ $cell['date'] === $now->format('Y-m-d') ? ' cal-today' : '' }}">
                      <a href="{{ $site['base'] }}/arquivo/{{ str_replace('-', '/', $cell['date']) }}/" aria-label="{{ $cell['day'] }}: {{ $cell['count'] }} {{ $cell['count'] === 1 ? 'notícia' : 'notícias' }}">
                        <span class="cal-num">{{ $cell['day'] }}</span>
                        <span class="cal-count">{{ $cell['count'] }}</span>
                      </a>
                    </td>
                  @else
                    <td class="cal-empty{{ $cell['date'] === $now->format('Y-m-d') ? ' cal-today' : '' }}"><span class="cal-num">{{ $cell['day'] }}</span></td>
                  @endif
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>
    @empty
      <p class="empty">Ainda não há notícias no arquivo.</p>
    @endforelse
  </div>
@endsection
