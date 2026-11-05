@use('App\Weather\WeatherCode')
@use('App\Weather\WeatherIcon')
@use('App\Site\Timeline')
@php($days = array_values(array_filter($weather['daily'] ?? [], fn ($day) => $day['date'] >= $now->format('Y-m-d'))))
@if ($days)
  @php($current = $weather['current'] ?? [])
  {{-- Um dia de cada vez, começando por hoje; o JS alterna (sem JS, fica só hoje). --}}
  <section class="weather" aria-label="Previsão do tempo em Itapetininga" data-rotate>
    @foreach (array_slice($days, 0, 3) as $day)
      @php($today = $day['date'] === $now->format('Y-m-d'))
      @php($code = $today && isset($current['code']) ? $current['code'] : $day['code'])
      <div class="weather-slide{{ $loop->first ? ' is-active' : '' }}"{{ $loop->first ? '' : ' hidden' }}>
        <b>{{ Timeline::shortDay($day['date'], $now) }}</b>
        <span class="wx" title="{{ WeatherCode::label($code) }}">{!! WeatherIcon::forCode($code) !!}<span class="visually-hidden">{{ mb_strtolower(WeatherCode::label($code)) }}</span></span>
        @if ($today && isset($current['temperature']))
          <span class="temp">{{ round($current['temperature']) }}°<span class="visually-hidden"> agora</span></span>
        @endif
        <span class="wx">{{ round($day['max'] ?? 0) }}° / {{ round($day['min'] ?? 0) }}°</span>
        @if ($day['rain_probability'] !== null)
          <span class="wx muted">{!! WeatherIcon::svg('drop') !!}<span class="visually-hidden">chuva </span>{{ $day['rain_probability'] }}%</span>
        @endif
      </div>
    @endforeach
  </section>
@endif
