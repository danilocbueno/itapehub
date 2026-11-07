<?php

namespace App\Camara;

/**
 * O que vai na capa da notícia de uma sessão (foto do plenário escurecida com
 * o número em destaque): "53ª", "Sessão Ordinária" e, quando há os dados da
 * sessão, os números (pedidos, votações de projetos, presença, duração).
 */
final class SessionCover
{
    /**
     * @param  list<string>  $stats
     */
    public function __construct(
        public string $number,
        public string $kind,
        public array $stats = [],
    ) {}

    /**
     * @param  string  $title  "53ª Sessão Ordinária de 2026"
     */
    public static function from(string $title, ?Session $session): self
    {
        $number = preg_match('~^(\d+[ªº])\s+(.+?)(?:\s+de\s+\d{4})?$~u', trim($title), $m) ? $m[1] : '';
        $kind = $m[2] ?? trim($title);

        return new self($number, $kind, $session !== null ? self::stats($session) : []);
    }

    /**
     * @return list<string>
     */
    private static function stats(Session $session): array
    {
        $stats = [];
        $requests = 0;

        foreach ($session->agenda as $section) {
            if (preg_match('~^(Indica|Requer)~u', $section['section'])) {
                $requests += count($section['items']);
            }
        }

        if ($requests > 0) {
            $stats[] = $requests.($requests === 1 ? ' pedido' : ' pedidos');
        }

        $urgent = 0;
        $approved = 0;

        foreach ($session->votes as $vote) {
            if (! str_starts_with($vote['title'], 'Projeto') || ! str_starts_with(mb_strtolower($vote['result']), 'aprovad')) {
                continue;
            }

            str_contains(mb_strtolower($vote['phase']), 'urgência') ? $urgent++ : $approved++;
        }

        if ($approved > 0) {
            $stats[] = $approved.($approved === 1 ? ' projeto aprovado' : ' projetos aprovados');
        }

        if ($urgent > 0) {
            $stats[] = $urgent.($urgent === 1 ? ' urgência aprovada' : ' urgências aprovadas');
        }

        $present = 0;
        $total = 0;

        foreach ($session->attendance as $tally) {
            $total += $tally['count'];
            $present += str_starts_with($tally['label'], 'Presente') ? $tally['count'] : 0;
        }

        if ($total > 0) {
            $stats[] = "{$present} de {$total} vereadores";
        }

        if ($duration = self::duration($session->time, $session->closedAt)) {
            $stats[] = $duration.' de sessão';
        }

        return $stats;
    }

    /**
     * "19:00" a "20:13" → "1h13".
     */
    private static function duration(string $start, ?string $end): ?string
    {
        if ($end === null || ! preg_match('~^(\d{1,2}):(\d{2})~', $start, $a) || ! preg_match('~^(\d{1,2}):(\d{2})~', $end, $b)) {
            return null;
        }

        $minutes = ((int) $b[1] * 60 + (int) $b[2]) - ((int) $a[1] * 60 + (int) $a[2]);

        if ($minutes <= 0) {
            return null;
        }

        return intdiv($minutes, 60) > 0 ? intdiv($minutes, 60).'h'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : $minutes.' min';
    }
}
