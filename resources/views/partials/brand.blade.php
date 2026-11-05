{{-- Nome em duas partes ("ItapêHub" → "Itapê" + "Hub"): a segunda em vermelho, sublinhada pela linha com paradas. --}}
@if (preg_match('/^(\S+?)(\p{Lu}\p{Ll}+)$/u', $site['name'], $parts))
  <a class="brand" href="{{ $site['base'] }}/"><span class="brand-a">{{ $parts[1] }}<span class="brand-line" aria-hidden="true"><i></i><i></i><i></i><i></i></span></span><span class="brand-b">{{ $parts[2] }}<span class="brand-line" aria-hidden="true"></span></span></a>
@else
  <a class="brand" href="{{ $site['base'] }}/">{{ $site['name'] }}</a>
@endif
