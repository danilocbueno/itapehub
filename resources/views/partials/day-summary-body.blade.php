{{-- Corpo do resumo do dia: usado na página e, com links absolutos, no item do RSS. --}}
@php($news = array_column($summary['news'], null, 'id'))
@php($own = rtrim($site['url'], '/').'/')
@if ($summary['kind'] === 'ia')
  <div class="session-story">
    <p class="session-lede">{{ $summary['article']['lide'] }}</p>
    @foreach ($summary['article']['blocos'] as $block)
      @if ($block['intertitulo'] !== '')
        <h2>{{ $block['intertitulo'] }}</h2>
      @endif
      @foreach ($block['paragrafos'] as $paragraph)
        <p>{{ $paragraph }}</p>
      @endforeach
      {{-- De onde saiu o bloco: a notícia completa está no veículo. --}}
      <ul class="summary-sources">
        @foreach ($block['noticias'] as $id)
          @isset($news[$id])
            <li><a href="{{ $news[$id]['url'] }}"@unless (str_starts_with($news[$id]['url'], $own)) target="_blank" rel="noopener"@endunless>{{ $news[$id]['title'] }}</a> <span>{{ implode(', ', $news[$id]['sources']) }}</span></li>
          @endisset
        @endforeach
      </ul>
    @endforeach
  </div>
@else
  <ul class="summary-list">
    @foreach ($summary['news'] as $item)
      <li><a href="{{ $item['url'] }}"@unless (str_starts_with($item['url'], $own)) target="_blank" rel="noopener"@endunless>{{ $item['title'] }}</a> <span>{{ implode(', ', $item['sources']) }} · {{ $item['time'] }}</span></li>
    @endforeach
  </ul>
@endif
