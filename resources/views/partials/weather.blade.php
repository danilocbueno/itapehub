@use('App\Weather\WeatherCode')
@use('App\Weather\WeatherIcon')
@use('App\Site\Timeline')
@php($days = array_values(array_filter($weather['daily'] ?? [], fn ($day) => $day['date'] >= $now->format('Y-m-d'))))
@if ($days)
  {{-- Um dia de cada vez, começando por hoje; o JS alterna (sem JS, fica só hoje). Todos com máxima e mínima. --}}
  <section class="weather" aria-label="Previsão do tempo em Itapetininga" data-rotate>
    @foreach (array_slice($days, 0, 3) as $day)
      @php($code = $day['code'])
      <div class="weather-slide{{ $loop->first ? ' is-active' : '' }}"{{ $loop->first ? '' : ' hidden' }}>
        <b>{{ Timeline::shortDay($day['date'], $now) }}</b>
        <span class="wx" title="{{ WeatherCode::label($code) }}">{!! WeatherIcon::forCode($code) !!}<span class="visually-hidden">{{ mb_strtolower(WeatherCode::label($code)) }}</span></span>
        <span class="wx">{{ round($day['max'] ?? 0) }}° / {{ round($day['min'] ?? 0) }}°</span>
        @if ($day['rain_probability'] !== null)
          <span class="wx muted">{!! WeatherIcon::svg('drop') !!}<span class="visually-hidden">chuva </span>{{ $day['rain_probability'] }}%</span>
        @endif
      </div>
    @endforeach
  </section>
@endif
