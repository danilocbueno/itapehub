<?php

namespace App\Site;

use App\Camara\SessionNews;
use App\Data\Item;
use App\Support\Text;

/**
 * Classifica notícias por tema a partir das palavras-chave de config/themes.php.
 */
final class Themes
{
    /**
     * @return array<string, array{name: string, description: string, pattern: string|null}>
     */
    public static function all(): array
    {
        return config('themes', []);
    }

    /**
     * Temas da notícia: primeiro os encontrados no título, depois os que só
     * aparecem no resumo, cada grupo na ordem de prioridade da configuração.
     * Sem nenhum, a notícia vai para o tema de reserva (Outras).
     *
     * @return list<string>
     */
    public static function for(Item $item): array
    {
        // As notícias das sessões da Câmara são sempre de política.
        if ($item->source === SessionNews::SOURCE) {
            return ['politica'];
        }

        $fromTitle = self::match(Text::fold($item->title));
        $fromSummary = self::match(Text::fold($item->summary));
        $themes = array_values(array_unique([...$fromTitle, ...$fromSummary]));

        if ($themes === [] && ($fallback = self::fallback()) !== null) {
            return [$fallback];
        }

        return $themes;
    }

    /**
     * Tema principal (o de maior prioridade), ou null.
     */
    public static function primary(Item $item): ?string
    {
        return self::for($item)[0] ?? null;
    }

    /**
     * O tema que recebe o que não entrou em nenhum outro (sem 'pattern').
     */
    public static function fallback(): ?string
    {
        foreach (self::all() as $slug => $theme) {
            if (($theme['pattern'] ?? null) === null) {
                return $slug;
            }
        }

        return null;
    }

    public static function isFallback(?string $slug): bool
    {
        return $slug !== null && $slug === self::fallback();
    }

    /**
     * @return list<string>
     */
    private static function match(string $text): array
    {
        $themes = [];

        foreach (self::all() as $slug => $theme) {
            if ($text !== '' && ($theme['pattern'] ?? null) !== null && preg_match($theme['pattern'], $text)) {
                $themes[] = $slug;
            }
        }

        return $themes;
    }
}
