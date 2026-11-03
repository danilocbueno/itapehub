<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Text
{
    /**
     * Converte um trecho de HTML (ou texto com entidades) em texto puro de uma linha.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $text = self::toUtf8($html);
        $text = preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $text) ?? $text;
        // Quebra de bloco vira espaço para não colar palavras.
        $text = preg_replace('~<(br|/p|/div|/li|/h\d|/figcaption|/td)\b[^>]*>~i', ' ', $text) ?? $text;
        $text = strip_tags($text);

        // Alguns feeds trazem entidades duplamente codificadas (&amp;quot;).
        for ($i = 0; $i < 2; $i++) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // ...e até tags codificadas como texto (&lt;p&gt;), que só aparecem agora.
        $text = strip_tags($text);

        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Resumo curto, cortado em fim de palavra.
     */
    public static function excerpt(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > $length * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B,;:.-–—").'…';
    }

    /**
     * Minúsculas e sem acentos, para comparações.
     */
    public static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    public static function toUtf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
}
