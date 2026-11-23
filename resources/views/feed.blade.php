{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>{{ $site['name'] }} · Resumo do dia</title>
    <link>{{ $site['url'] }}/</link>
    <description>As notícias de Itapetininga reunidas em um resumo por dia.</description>
    <language>pt-BR</language>
    <lastBuildDate>{{ $now->toRfc2822String() }}</lastBuildDate>
    <atom:link href="{{ $site['url'] }}/feed.xml" rel="self" type="application/rss+xml"/>
@foreach ($summaries as $summary)
@php($item = $summary['item'])
@php($body = view('partials.day-summary-body', ['summary' => $summary['summary'], 'site' => $site])->render())
    <item>
      <title>{{ $item->title }}</title>
      <link>{{ $item->url }}</link>
      <guid isPermaLink="true">{{ $item->url }}</guid>
      <pubDate>{{ \Carbon\CarbonImmutable::parse($summary['summary']['written_at'] ?? $item->date())->toRfc2822String() }}</pubDate>
      <description>{{ '<p>'.e($item->summary).'</p>'.$body }}</description>
    </item>
@endforeach
  </channel>
</rss>
