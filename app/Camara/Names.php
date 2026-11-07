<?php

namespace App\Camara;

/**
 * Troca os nomes do SisCam pelos nomes curtos de config/camara.php, antes de
 * escrever a notícia (por IA ou pelo modelo fixo).
 */
final class Names
{
    /**
     * @param  array<string, string>  $short  nome no SisCam => nome curto
     */
    public function __construct(private array $short) {}

    public static function fromConfig(): self
    {
        return new self((array) config('camara.vereadores'));
    }

    public function short(string $name): string
    {
        return $this->short[trim($name)] ?? trim($name);
    }

    /**
     * "Fulano de Tal, Beltrano" → "Fulano, Beltrano".
     */
    public function authors(string $authors): string
    {
        if (trim($authors) === '') {
            return '';
        }

        return implode(', ', array_map(fn (string $name) => $this->short($name), explode(',', $authors)));
    }

    /**
     * Nomes curtos conhecidos (para conferir a autoria no texto gerado).
     *
     * @return list<string>
     */
    public function known(): array
    {
        return array_values(array_unique($this->short));
    }

    /**
     * A sessão com todos os nomes já encurtados.
     */
    public function apply(Session $session): Session
    {
        $copy = clone $session;
        $copy->attendance = array_map(fn (array $t) => [...$t, 'names' => array_map(fn (string $n) => $this->short($n), $t['names'])], $session->attendance);
        $copy->votes = array_map(fn (array $v) => [
            ...$v,
            'author' => $this->authors($v['author']),
            'tallies' => array_map(fn (array $t) => [...$t, 'names' => array_map(fn (string $n) => $this->short($n), $t['names'])], $v['tallies']),
        ], $session->votes);
        $copy->agenda = array_map(fn (array $s) => [
            ...$s,
            'items' => array_map(fn (array $i) => [...$i, 'author' => $this->authors($i['author'])], $s['items']),
        ], $session->agenda);

        return $copy;
    }
}
