<?php

namespace App\Resumo;

use App\Data\Source;
use App\Programas\RateLimited;
use App\Site\Cluster;
use App\Storage\JsonFile;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Escreve o resumo de cada dia que terminou (data/resumos/AAAA-MM-DD.json).
 *
 * O dia só é resumido a partir de after_hour do dia seguinte, para a coleta da
 * madrugada trazer as últimas notícias. O Gemini é chamado uma vez por execução;
 * se falhar ou o texto não passar na conferência, tenta de novo nas próximas.
 * Sem a chave, ou se não conseguir até fallback_hour do dia seguinte (ou depois
 * de max_attempts), publica a lista simples com os títulos: o RSS não fica sem
 * o resumo do dia. Recusa por cota ou sobrecarga não conta como tentativa.
 *
 * Cada arquivo guarda as notícias usadas ("news"), para os links da página não
 * mudarem depois. "kind": "ia" (texto do Gemini), "lista" ou "vazio" (dia sem notícias).
 */
final class DayDesk
{
    public function __construct(private string $directory, private ?DayWriter $writer) {}

    /**
     * @param  list<Cluster>  $clusters  as notícias (sem os resumos), mais recentes primeiro
     * @param  array<string, Source>  $sources
     * @return list<array{date: string, kind: string|null, by: string|null, error: string|null}>
     */
    public function run(array $clusters, array $sources, CarbonImmutable $now): array
    {
        $done = [];
        $asked = false;

        foreach ($this->pending($now) as $day) {
            $file = $this->directory.'/'.$day->format('Y-m-d').'.json';
            $summary = JsonFile::read($file) ?? ['date' => $day->format('Y-m-d'), 'attempts' => 0];
            $deadline = $day->addDay()->setTime((int) config('itape.resumo.fallback_hour'), 0);
            // As notícias são lidas de novo a cada tentativa: a coleta pode ter trazido mais.
            $news = self::snapshot($clusters, $sources, $day);

            if ($news === []) {
                $summary = [...$summary, 'kind' => 'vazio', 'news' => []];
            } elseif ($this->writer === null) {
                $summary = $this->asList($summary, $news, $now);
            } elseif (! $asked) {
                $asked = true;
                $summary = $this->ask($this->writer, $summary, $day, $news, $now);

                if (! isset($summary['kind']) && ($now->gte($deadline) || $summary['attempts'] >= (int) config('itape.resumo.max_attempts'))) {
                    $summary = $this->asList($summary, $news, $now);
                }
            } elseif ($now->gte($deadline)) {
                $summary = $this->asList($summary, $news, $now);
            } else {
                continue;
            }

            JsonFile::write($file, $summary);
            $done[] = [
                'date' => $summary['date'],
                'kind' => $summary['kind'] ?? null,
                'by' => $summary['written_by'] ?? null,
                'error' => isset($summary['kind']) ? null : ($summary['error'] ?? null),
            ];
        }

        return $done;
    }

    /**
     * Dias terminados, desde "since", dentro da janela de "days", sem resumo
     * publicado, do mais novo para o mais antigo.
     *
     * @return list<CarbonImmutable>
     */
    private function pending(CarbonImmutable $now): array
    {
        $since = CarbonImmutable::parse((string) config('itape.resumo.since'), $now->getTimezone())->startOfDay();
        $oldest = $now->startOfDay()->subDays((int) config('itape.resumo.days'));
        $day = $now->subHours((int) config('itape.resumo.after_hour'))->startOfDay()->subDay();
        $days = [];

        for (; $day->gte($since) && $day->gte($oldest); $day = $day->subDay()) {
            $summary = JsonFile::read($this->directory.'/'.$day->format('Y-m-d').'.json');

            if (! isset($summary['kind'])) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $news
     * @return array<string, mixed>
     */
    private function ask(DayWriter $writer, array $summary, CarbonImmutable $day, array $news, CarbonImmutable $now): array
    {
        $attempts = (int) ($summary['attempts'] ?? 0);
        $summary['attempts'] = $attempts + 1;

        try {
            $answer = $writer->write($day, $news);
            $article = DayStory::check($answer['data'], array_column($news, 'id'), $day);

            unset($summary['error']);

            return [...$summary, 'kind' => 'ia', 'article' => $article, 'news' => $news, 'written_by' => 'gemini:'.$answer['model'], 'written_at' => $now->toIso8601String()];
        } catch (RateLimited $e) {
            $summary['attempts'] = $attempts;
            $summary['error'] = mb_strimwidth($e->getMessage(), 0, 400, '…');
        } catch (Throwable $e) {
            $summary['error'] = mb_strimwidth($e->getMessage(), 0, 400, '…');
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<array<string, mixed>>  $news
     * @return array<string, mixed>
     */
    private function asList(array $summary, array $news, CarbonImmutable $now): array
    {
        return [...$summary, 'kind' => 'lista', 'news' => $news, 'written_by' => 'lista', 'written_at' => $now->toIso8601String()];
    }

    /**
     * As notícias do dia (do primeiro ao último minuto), as mais repercutidas
     * primeiro (mais veículos), até max_items. Cada uma ganha um id curto (n1, n2…),
     * que o Gemini usa para dizer de onde saiu cada bloco.
     *
     * @param  list<Cluster>  $clusters
     * @param  array<string, Source>  $sources
     * @return list<array{id: string, title: string, summary: string, url: string, image: string|null, sources: list<string>, time: string}>
     */
    public static function snapshot(array $clusters, array $sources, CarbonImmutable $day): array
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();
        $selected = array_values(array_filter($clusters, fn (Cluster $c) => $c->date()->between($start, $end)));

        usort($selected, fn (Cluster $a, Cluster $b) => [count($b->sources()), $b->date()] <=> [count($a->sources()), $a->date()]);

        $news = [];

        foreach (array_slice($selected, 0, (int) config('itape.resumo.max_items')) as $i => $cluster) {
            $item = $cluster->primary;
            $news[] = [
                'id' => 'n'.($i + 1),
                'title' => $item->title,
                'summary' => $item->summary,
                'url' => $item->url,
                'image' => $cluster->image(),
                'sources' => array_map(fn (string $key) => isset($sources[$key]) ? $sources[$key]->short : $key, $cluster->sources()),
                'time' => $item->date()->setTimezone($day->getTimezone())->format('H:i'),
            ];
        }

        return $news;
    }
}
