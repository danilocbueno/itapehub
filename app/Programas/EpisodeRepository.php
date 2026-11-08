<?php

namespace App\Programas;

use App\Storage\JsonFile;

/**
 * Um arquivo por episódio em data/programas/<programa>/<videoId>.json, com os
 * dados do vídeo e, depois que o Gemini escrever, a notícia ("article").
 */
final class EpisodeRepository
{
    public function __construct(private string $directory) {}

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $program, string $video): ?array
    {
        return JsonFile::read($this->path($program, $video));
    }

    /**
     * @param  array<string, mixed>  $episode
     */
    public function save(array $episode): void
    {
        JsonFile::write($this->path((string) $episode['program'], (string) $episode['video']), $episode);
    }

    /**
     * @return list<array<string, mixed>> mais recentes primeiro
     */
    public function all(string $program): array
    {
        $episodes = [];

        foreach (glob($this->directory.'/'.$program.'/*.json') ?: [] as $file) {
            $episode = JsonFile::read($file);

            if ($episode !== null && isset($episode['video'], $episode['date'])) {
                $episodes[] = $episode;
            }
        }

        usort($episodes, fn (array $a, array $b) => [$b['date'], $b['video']] <=> [$a['date'], $a['video']]);

        return $episodes;
    }

    private function path(string $program, string $video): string
    {
        return $this->directory.'/'.$program.'/'.$video.'.json';
    }
}
