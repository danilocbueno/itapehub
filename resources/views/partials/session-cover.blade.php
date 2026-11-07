{{-- Capa da notícia de uma sessão: foto do plenário escurecida com o número em destaque.
     Os tamanhos acompanham a largura (container queries): no card pequeno, só o número. --}}
<div class="session-cover{{ ($large ?? false) ? ' is-large' : '' }}">
  <img src="{{ $photo }}" alt="" loading="lazy" decoding="async">
  <div class="session-cover-text">
    <span class="session-cover-kind">{{ $cover->kind }}</span>
    <span class="session-cover-number">{{ $cover->number }}</span>
    <span class="session-cover-line" aria-hidden="true"><i></i><i></i><i></i></span>
    <span class="session-cover-date">{{ \App\Site\Timeline::dateline($date) }}</span>
    @if (($large ?? false) && $cover->stats)
      <span class="session-cover-stats">{{ implode(' · ', $cover->stats) }}</span>
    @endif
  </div>
</div>
