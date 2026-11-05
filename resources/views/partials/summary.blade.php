{{-- Resumo do dia na home: destaques numerados e o resto do dia por período. --}}
@php($thumbError = "this.closest('a').classList.add('noimg'); this.parentNode.remove()")
<section class="summary" aria-labelledby="summary-title">
  <div class="summary-head">
    <h2 id="summary-title">Resumo do dia</h2>
    <span class="summary-meta">{{ $summary['total'] }} {{ $summary['total'] === 1 ? 'notícia' : 'notícias' }} · atualizado {{ $now->format('G\hi') }}</span>
  </div>

  @if ($summary['periods'])
    <p class="summary-label">Os destaques</p>
  @endif
  <ol class="summary-list">
    @foreach ($summary['highlights'] as $cluster)
      @php($item = $cluster->primary)
      @php($image = $cluster->image())
      @php($names = array_map(fn ($key) => ($sources[$key] ?? null)?->short ?? $key, $cluster->sources()))
      <li>
        <a class="summary-item{{ $image ? '' : ' noimg' }}" href="{{ $item->url }}" target="_blank" rel="noopener">
          <span class="summary-num">{{ $loop->iteration }}</span>
          <span class="summary-text">
            <span class="summary-title">{{ $item->title }}</span>
            <span class="summary-sub">{{ $item->date()->setTimezone($now->getTimezone())->format('G\hi') }} ·
              @if (count($names) > 1)<b>{{ count($names) }} veículos</b>: @endif{{ implode(', ', $names) }}</span>
          </span>
          @if ($image)
            <span class="summary-thumb"><img src="{{ $image }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="{{ $thumbError }}"></span>
          @endif
        </a>
      </li>
    @endforeach
  </ol>

  @foreach ($summary['periods'] as $period)
    {{-- Só o período mais recente começa aberto. --}}
    <details class="summary-period"@if ($loop->first) open @endif>
      <summary>
        <span class="summary-period-name">{{ $period['label'] }}</span>
        <span class="summary-meta">{{ count($period['clusters']) }} {{ count($period['clusters']) === 1 ? 'notícia' : 'notícias' }}</span>
      </summary>
      @foreach ($period['clusters'] as $cluster)
        @php($item = $cluster->primary)
        @php($image = $cluster->image())
        <a class="summary-row{{ $image ? '' : ' noimg' }}" href="{{ $item->url }}" target="_blank" rel="noopener">
          <span class="summary-time">{{ $item->date()->setTimezone($now->getTimezone())->format('G\hi') }}</span>
          <span class="summary-row-title">{{ $item->title }}</span>
          @if ($image)
            <span class="summary-thumb"><img src="{{ $image }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="{{ $thumbError }}"></span>
          @endif
        </a>
      @endforeach
    </details>
  @endforeach
</section>
