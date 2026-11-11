<?php

namespace App\Programas;

use App\Data\Source;
use Carbon\CarbonImmutable;

/**
 * Um programa em vídeo de config/programas.php.
 */
final class Program
{
    /**
     * @param  list<string>  $pages  páginas do canal com a lista de vídeos
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $station,
        public array $pages,
        public string $titlePattern,
        public int $minMinutes,
        public string $start,
        public string $color = '#64748b',
        public ?float $fps = null,
        public bool $paused = false,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $programs = [];

        foreach ((array) config('programas') as $key => $p) {
            $programs[$key] = new self(
                key: $key,
                name: $p['name'],
                station: $p['station'],
                pages: array_values((array) $p['pages']),
                titlePattern: $p['title'],
                minMinutes: (int) $p['min_minutes'],
                start: $p['start'],
                color: $p['color'] ?? '#64748b',
                fps: isset($p['fps']) ? (float) $p['fps'] : null,
                paused: (bool) ($p['paused'] ?? false),
            );
        }

        return $programs;
    }

    /**
     * Data e tema do título do vídeo, ou null se o vídeo não for um episódio.
     * A expressão tem os grupos "date" (DD/MM/AAAA) e, se o programa tiver, "topic":
     * "Telescópio - 06/11/2026 - Ação voluntária" → [2026-11-06, "Ação voluntária"].
     *
     * @return array{date: CarbonImmutable, topic: string}|null
     */
    public function parseTitle(string $title): ?array
    {
        if (! preg_match($this->titlePattern, trim($title), $m) || ! isset($m['date'])) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!d/m/Y', $m['date'], 'America/Sao_Paulo');

        return $date === null ? null : ['date' => $date, 'topic' => trim($m['topic'] ?? '', " \t\"“”")];
    }

    public function sourceKey(): string
    {
        return 'programa-'.$this->key;
    }

    /**
     * "Fonte" usada no chapéu do card ("TV Itapê · Telescópio"). Não é coletada nem aparece em /fontes/.
     */
    public function source(): Source
    {
        return new Source(
            key: $this->sourceKey(),
            name: "{$this->name} ({$this->station})",
            short: "{$this->station} · {$this->name}",
            // O tipo não é usado: essas notícias não passam pela coleta.
            type: 'rss',
            url: $this->pages[0],
            homepage: $this->pages[0],
            color: $this->color,
            enabled: false,
        );
    }
}
