{{-- Slides com os pôsteres das sessões que ainda vão começar hoje (ou do próximo dia). --}}
{{-- Na home, o título segue o dos "Próximos eventos": em itálico, com o fio e o link no fim da linha. --}}
@php($home = $home ?? false)
<section class="cinema-strip{{ $home ? ' day' : '' }}" aria-labelledby="hoje-no-cinema">
  <div class="{{ $home ? 'upcoming-head' : 'cinema-strip-head' }}">
    <h2 id="hoje-no-cinema">{{ $cinemaNext['date']->isSameDay($now) ? 'Hoje' : ($cinemaNext['date']->isSameDay($now->addDay()) ? 'Amanhã' : ucfirst(\App\Site\Timeline::longDate($cinemaNext['date']))) }} no cinema</h2>
    <a @if ($home) class="upcoming-more" @endif href="{{ $site['base'] }}/cinema/">Programação completa</a>
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
