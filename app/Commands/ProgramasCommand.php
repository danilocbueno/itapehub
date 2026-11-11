<?php

namespace App\Commands;

use App\Programas\EpisodeFinder;
use App\Programas\EpisodeRepository;
use App\Programas\Program;
use App\Programas\ProgramDesk;
use App\Programas\VideoWriter;
use Carbon\CarbonImmutable;
use LaravelZero\Framework\Commands\Command;
use Throwable;

class ProgramasCommand extends Command
{
    protected $signature = 'programas';

    protected $description = 'Procura episódios novos dos programas em vídeo (config/programas.php) e escreve a notícia de cada um com o Gemini';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $episodes = new EpisodeRepository(config('itape.paths.data').'/programas');
        $writer = VideoWriter::fromConfig();

        if ($writer === null) {
            $this->line('Programas: sem GEMINI_API_KEY, os episódios ficam guardados sem notícia.');
        }

        // Pausado: não procura episódios novos nem escreve (as notícias já publicadas continuam no site).
        $programs = array_filter(Program::all(), fn (Program $program) => ! $program->paused);

        foreach ($programs as $program) {
            try {
                $found = (new EpisodeFinder($episodes))->find($program, $now);
                $this->info(sprintf('%s: %d episódios no canal · %d novos', $program->name, $found['listed'], $found['new']));

                foreach ($found['errors'] as $error) {
                    $this->warn("  {$program->name}: {$error}");
                }
            } catch (Throwable $e) {
                // O YouTube fora do ar (ou com a página mudada) não deve impedir o resto da publicação.
                $this->warn("{$program->name}: {$e->getMessage()}");
            }
        }

        if ($writer === null) {
            return self::SUCCESS;
        }

        foreach ((new ProgramDesk($episodes, $writer))->write($programs, $now) as $written) {
            if ($written['error'] === null) {
                $this->line("Notícia do {$written['program']} ({$written['video']}) escrita por {$written['by']}");
            } else {
                $this->warn("{$written['program']} ({$written['video']}) sem notícia (tenta de novo depois): {$written['error']}");
            }
        }

        return self::SUCCESS;
    }
}
