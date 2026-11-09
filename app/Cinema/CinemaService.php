<?php

namespace App\Cinema;

use App\Http\Fetcher;
use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Atualiza data/cinema.json com a programação do cinema da cidade, respeitando
 * um tempo mínimo entre consultas. Em caso de falha, mantém os dados anteriores.
 */
final class CinemaService
{
    public function __construct(private Fetcher $fetcher) {}

    public function refresh(string $path, CarbonImmutable $now, bool $force = false): string
    {
        $cached = JsonFile::read($path);

        if (! $force && isset($cached['fetched_at'])
            && CarbonImmutable::parse($cached['fetched_at'])->addMinutes((int) config('itape.cinema.ttl_minutes'))->greaterThan($now)) {
            return 'em cache';
        }

        $url = (string) config('itape.cinema.url');
        $result = $this->fetcher->get($url);

        if (! $result->ok()) {
            return 'erro: '.($result->error ?? "HTTP {$result->status}");
        }

        try {
            $days = CinemaParser::days($result->body, $now);
            // Só os próximos dias: a página vende ingressos com semanas de antecedência.
            $days = array_slice(array_values(array_filter($days, fn (array $d) => $d['date'] >= $now->format('Y-m-d'))), 0, (int) config('itape.cinema.days'));

            JsonFile::write($path, [
                'fetched_at' => $now->toIso8601String(),
                'name' => (string) config('itape.cinema.name'),
                'url' => $url,
                'days' => $days,
            ]);
        } catch (Throwable $e) {
            return 'erro: '.$e->getMessage();
        }

        return count($days).' dias de programação';
    }
}
