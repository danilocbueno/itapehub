<?php

namespace App\Camara;

use App\Http\Fetcher;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Busca no SisCam as sessões recentes e guarda presença, votações e roteiro.
 * Uma sessão é buscada de novo até o SisCam registrar o encerramento.
 */
final class SiscamCollector
{
    public function __construct(private Fetcher $fetcher) {}

    /**
     * @return array{listed: int, saved: int, complete: int}
     */
    public function collect(SessionRepository $sessions, CarbonImmutable $now): array
    {
        $baseUrl = rtrim((string) config('itape.camara.url'), '/');
        $parser = new SiscamParser($baseUrl);
        $list = $this->fetcher->get($baseUrl.'/sessoes');

        if (! $list->ok()) {
            throw new RuntimeException('SisCam indisponível: '.($list->error ?? 'HTTP '.$list->status));
        }

        $since = $now->subDays((int) config('itape.camara.days'))->format('Y-m-d');
        $recent = array_filter($parser->sessions($list->body), fn (array $s) => $s['date'] !== '' && $s['date'] >= $since && $s['date'] <= $now->format('Y-m-d'));
        $saved = 0;
        $complete = 0;

        foreach ($recent as $listed) {
            if ($sessions->find($listed['id'])?->complete()) {
                $complete++;

                continue;
            }

            $page = $this->fetcher->get($baseUrl.'/Sessoes/Sessao/'.$listed['id']);

            if (! $page->ok()) {
                continue;
            }

            $session = $parser->session($listed['id'], $page->body);

            if ($session->agendaUrl !== null) {
                $agenda = $this->fetcher->get($session->agendaUrl);
                $session->agenda = $agenda->ok() ? $parser->agenda($agenda->body) : [];
            }

            if ($sessions->save($session)) {
                $saved++;
            }

            $complete += $session->complete() ? 1 : 0;
        }

        return ['listed' => count($recent), 'saved' => $saved, 'complete' => $complete];
    }
}
