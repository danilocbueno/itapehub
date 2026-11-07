<?php

namespace App\Commands;

use App\Camara\SessionRepository;
use App\Camara\SiscamCollector;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class CamaraCommand extends Command
{
    protected $signature = 'camara';

    protected $description = 'Busca no SisCam as sessões recentes da Câmara (presença, votações e roteiro)';

    public function handle(SiscamCollector $collector): int
    {
        $sessions = new SessionRepository(config('itape.paths.data').'/camara/sessoes');

        try {
            $report = $collector->collect($sessions, CarbonImmutable::now());
        } catch (Throwable $e) {
            // A Câmara fora do ar não deve impedir o resto da publicação.
            $this->warn('Câmara: '.$e->getMessage());

            return self::SUCCESS;
        }

        $this->info(sprintf('Câmara: %d sessões recentes · %d atualizadas · %d encerradas', $report['listed'], $report['saved'], $report['complete']));

        return self::SUCCESS;
    }
}
