<?php

namespace App\Sources;

use App\Data\Item;
use App\Data\Source;
use App\Support\Dates;
use App\Support\Text;
use App\Support\Url;
use SimplePie\Item as FeedItem;
use SimplePie\SimplePie;

final class RssAdapter implements SourceAdapter
{
    public function parse(string $body, Source $source): array
    {
        $feed = new SimplePie;
        $feed->set_raw_data($body);
        $feed->enable_cache(false);
        $feed->enable_order_by_date(false);
        $feed->set_stupidly_fast(true);

        // O SimplePie emite um E_USER_NOTICE para conteúdo inválido; o erro é tratado abaixo.
        if (! @$feed->init() || $feed->error()) {
            $error = $feed->error();

            throw new ParseException('Feed inválido: '.(is_array($error) ? implode('; ', $error) : (string) $error));
        }

        $items = [];

        foreach ($feed->get_items() as $entry) {
            $url = $entry->get_permalink();

            if (! $url) {
                continue;
            }

            $description = (string) $entry->get_description(true);
            $content = (string) $entry->get_content(true);

            $item = new Item(
                source: $source->key,
                url: Url::clean(Url::absolute($url, $source->url)),
                title: Text::clean($entry->get_title()),
                summary: $this->text($description !== '' ? $description : $content, $source),
                image: $this->image($entry, $description.$content, $source),
                publishedAt: Dates::parse($entry->get_date('c') ?: null, $source->naiveTimezone),
            );
            $item->content = $this->text($content, $source);

            $items[] = $item;
        }

        if ($items === [] && ! str_contains($body, '<channel') && ! str_contains($body, '<feed')) {
            throw new ParseException('Resposta não parece um feed RSS/Atom.');
        }

        return $items;
    }

    /**
     * Texto da descrição sem legenda de foto, rodapé do WordPress e
     * frases fixas configuradas na fonte.
     */
    private function text(string $html, Source $source): string
    {
        // O SimplePie pode envolver a descrição em <div>.
        if ($source->captionLines > 0 && preg_match('~^\s*(<div>\s*)?<img\b[^>]*>\s*(<br\s*/?>)?~i', $html, $m)) {
            $lines = preg_split('/\R/u', substr($html, strlen($m[0]))) ?: [];
            $lines = array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));
            $withoutCaption = implode("\n", array_slice($lines, $source->captionLines));

            // Se a descrição era só a legenda, melhor mantê-la do que ficar sem resumo.
            if (Text::clean($withoutCaption) !== '') {
                $html = $withoutCaption;
            }
        }

        $text = Text::clean($html);
        // Rodapé padrão de feeds WordPress.
        $text = preg_replace('/\s*(O post|The post) .{1,300}? (apareceu primeiro em|é de autoria de|appeared first on|first appeared on) .{1,80}$/u', '', $text) ?? $text;
        // Marca de resumo cortado do WordPress: "[…]".
        $text = preg_replace('/\s*\[(…|\.\.\.)\]\s*$/u', '', $text) ?? $text;

        foreach ($source->ignore as $pattern) {
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function image(FeedItem $entry, string $html, Source $source): ?string
    {
        foreach ($entry->get_enclosures() ?? [] as $enclosure) {
            $link = $enclosure->get_link();
            $type = (string) $enclosure->get_type();
            $medium = (string) $enclosure->get_medium();

            if ($link && ($medium === 'image' || str_starts_with($type, 'image/') || preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $link))) {
                return Url::absolute($link, $source->url);
            }
        }

        if (preg_match('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m)) {
            return Url::absolute(html_entity_decode($m[1]), $source->url);
        }

        return null;
    }
}
