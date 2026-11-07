<?php

namespace App\Commands;

use App\Camara\GeminiWriter;
use App\Camara\Names;
use App\Camara\NewsDesk;
use App\Camara\SessionRepository;
use App\Camara\SiscamCollector;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class CamaraCommand extends Command
{
    protected $signature = 'camara';

    protected $description = 'Busca no SisCam as sessões recentes da Câmara e escreve a notícia de cada sessão encerrada';

    public function handle(SiscamCollector $collector): int
    {
        $now = CarbonImmutable::now();
        $dataPath = config('itape.paths.data').'/camara';
        $sessions = new SessionRepository($dataPath.'/sessoes');

        try {
            $report = $collector->collect($sessions, $now);
            $this->info(sprintf('Câmara: %d sessões recentes · %d atualizadas · %d encerradas', $report['listed'], $report['saved'], $report['complete']));
        } catch (Throwable $e) {
            // A Câmara fora do ar não deve impedir o resto da publicação.
            $this->warn('Câmara: '.$e->getMessage());
        }

        $gemini = GeminiWriter::fromConfig();

        if ($gemini === null) {
            $this->line('Notícias da Câmara: sem GEMINI_API_KEY, usando o modelo fixo.');
        }

        $desk = new NewsDesk($dataPath.'/noticias', Names::fromConfig(), $gemini);

        foreach ($desk->write($sessions->all(), $now) as $written) {
            $this->line(sprintf('Notícia da sessão %d escrita por %s', $written['session'], $written['by']));

            if ($written['error'] !== null) {
                $this->warn('  Gemini falhou, usado o modelo fixo: '.$written['error']);
            }
        }

        return self::SUCCESS;
    }
}
