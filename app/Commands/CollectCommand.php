<?php

namespace App\Commands;

use App\Cinema\CinemaService;
use App\Data\Source;
use App\Pipeline\Collector;
use App\Pipeline\TitleRepairer;
use App\Storage\ItemRepository;
use App\Storage\StateRepository;
use App\Weather\WeatherService;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;

class CollectCommand extends Command
{
    protected $signature = 'collect
        {--source=* : Coletar só estas fontes (chaves de config/sources.php)}
        {--skip-weather : Não atualizar previsão do tempo, alertas e cinema}
        {--force-weather : Ignorar o cache da previsão, dos alertas e do cinema}';

    protected $description = 'Coleta as fontes de notícias, a previsão do tempo e os alertas do INMET';

    public function handle(Collector $collector, TitleRepairer $repairer, WeatherService $weather, CinemaService $cinema): int
    {
        $now = CarbonImmutable::now();
        $dataPath = config('itape.paths.data');
        $sources = Source::enabled();

        if ($only = $this->option('source')) {
            $sources = array_intersect_key(Source::all(), array_flip($only));
        }

        $items = new ItemRepository($dataPath.'/items');
        $state = new StateRepository($dataPath.'/state.json');

        $reports = $collector->collect($sources, $items, $state, $now);
        $repaired = $repairer->repair(
            $items, $sources, $now,
            (int) config('itape.pipeline.repair_limit'),
            (int) config('itape.pipeline.max_age_days'),
        );

        $this->table(
            ['Fonte', 'Situação', 'Lidos', 'Novos', 'Filtrados'],
            array_map(fn ($report) => [
                $report->source,
                $report->error !== null ? '<fg=red>'.mb_strimwidth($report->error, 0, 70, '…').'</>' : $report->status,
                $report->parsed,
                $report->new,
                $report->filtered,
            ], array_values($reports)),
        );

        $changed = $items->save();
        $state->save();

        $this->info(sprintf('%d notícias novas · %d no total%s', array_sum(array_map(fn ($r) => $r->new, $reports)), $items->count(), $changed ? '' : ' · nada mudou'));

        if ($repaired > 0) {
            $this->line("Títulos corrigidos: {$repaired}");
        }

        if (! $this->option('skip-weather')) {
            $force = (bool) $this->option('force-weather');
            $this->line('Tempo: '.$weather->refreshWeather($dataPath.'/weather.json', $now, $force));
            $this->line('Alertas INMET: '.$weather->refreshAlerts($dataPath.'/alerts.json', $now, $force));
            $this->line('Cinema: '.$cinema->refresh($dataPath.'/cinema.json', $now, $force));
        }

        // Falha de uma fonte não deve derrubar a publicação; só falha se todas falharem.
        $failed = array_filter($reports, fn ($report) => $report->failed());

        return $reports !== [] && count($failed) === count($reports) ? self::FAILURE : self::SUCCESS;
    }
}
