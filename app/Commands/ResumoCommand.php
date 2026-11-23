<?php

namespace App\Commands;

use App\Resumo\DayDesk;
use App\Resumo\DayWriter;
use App\Site\SiteBuilder;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;

class ResumoCommand extends Command
{
    protected $signature = 'resumo';

    protected $description = 'Escreve o resumo de cada dia que terminou (Gemini, ou a lista simples de títulos), publicado no site e no RSS';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $dataPath = (string) config('itape.paths.data');
        $writer = DayWriter::fromConfig();

        if ($writer === null) {
            $this->line('Resumo do dia: sem GEMINI_API_KEY, publica a lista simples de títulos.');
        }

        $news = SiteBuilder::news($dataPath, (string) config('itape.site.url'));
        $done = (new DayDesk($dataPath.'/resumos', $writer))->run($news['clusters'], $news['sources'], $now);

        foreach ($done as $day) {
            $date = CarbonImmutable::parse($day['date'])->format('d/m');
            match ($day['kind']) {
                'ia' => $this->info("Resumo de {$date} escrito por {$day['by']}"),
                'lista' => $this->info("Resumo de {$date} publicado como lista de títulos"),
                'vazio' => $this->line("Resumo de {$date}: nenhuma notícia no dia"),
                default => $this->warn("Resumo de {$date} sem texto (tenta de novo depois): {$day['error']}"),
            };
        }

        if ($done === []) {
            $this->line('Resumo do dia: nada pendente.');
        }

        return self::SUCCESS;
    }
}
