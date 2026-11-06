<?php

use App\Site\Calendar;

describe('Calendário do arquivo', function () {
    it('começa no domingo e deixa vazias as casas fora do mês', function () {
        // Outubro de 2026 começa numa quinta e termina num sábado.
        $weeks = Calendar::weeks('2026-10', []);

        expect($weeks)->toHaveCount(5)
            ->and(array_slice($weeks[0], 0, 4))->toBe([null, null, null, null])
            ->and($weeks[0][4]['day'])->toBe(1)
            ->and($weeks[4][6]['day'])->toBe(31);
    });

    it('completa a última semana com casas vazias', function () {
        // Novembro de 2026 começa num domingo e termina numa segunda.
        $weeks = Calendar::weeks('2026-11', []);

        expect($weeks[0][0]['date'])->toBe('2026-11-01')
            ->and($weeks)->toHaveCount(5)
            ->and($weeks[4][1]['day'])->toBe(30)
            ->and(array_slice($weeks[4], 2))->toBe([null, null, null, null, null]);
    });

    it('traz a quantidade de notícias de cada dia', function () {
        $weeks = Calendar::weeks('2026-10', ['2026-10-25' => 9, '2026-10-24' => 4]);
        $days = array_column(array_filter(array_merge(...$weeks)), 'count', 'date');

        expect($days['2026-10-25'])->toBe(9)
            ->and($days['2026-10-24'])->toBe(4)
            ->and($days['2026-10-23'])->toBe(0);
    });
});
