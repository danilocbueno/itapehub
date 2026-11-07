<?php

namespace App\Camara;

use InvalidArgumentException;

/**
 * A notícia de uma sessão, no formato pedido em resources/prompts/camara-sessao.md
 * (o mesmo para o texto do Gemini e para o modelo fixo).
 *
 * @phpstan-type Section array{tema: string, itens: list<string>}
 */
final class Article
{
    /**
     * @param  list<string>  $resumo
     * @param  list<array{tema: string, itens: list<string>}>  $secoes
     * @param  list<string>  $tambem
     * @param  list<array{documento: string, motivo: string}>  $omitidos
     */
    public function __construct(
        public string $titulo,
        public string $linhaFina,
        public array $resumo,
        public array $secoes,
        public array $tambem = [],
        public array $omitidos = [],
    ) {}

    /**
     * Esquema JSON da resposta pedida ao Gemini.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];

        return [
            'type' => 'object',
            'properties' => [
                'titulo' => ['type' => 'string', 'description' => 'Título da notícia, até 90 caracteres.'],
                'linha_fina' => ['type' => 'string', 'description' => 'Linha fina, até 160 caracteres.'],
                'resumo' => [...$strings, 'description' => 'De três a cinco tópicos.'],
                'secoes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['tema' => ['type' => 'string'], 'itens' => $strings],
                        'required' => ['tema', 'itens'],
                    ],
                ],
                'tambem' => $strings,
                'omitidos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['documento' => ['type' => 'string'], 'motivo' => ['type' => 'string']],
                        'required' => ['documento', 'motivo'],
                    ],
                ],
            ],
            'required' => ['titulo', 'linha_fina', 'resumo', 'secoes', 'tambem', 'omitidos'],
        ];
    }

    /**
     * Lê e confere uma notícia. Recusa o texto se faltar algo, se passar dos
     * limites ou se citar entre parênteses alguém que não é vereador conhecido.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $knownAuthors
     */
    public static function fromArray(array $data, array $knownAuthors = []): self
    {
        $text = fn (mixed $value, int $max, string $field): string => is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max
            ? trim($value)
            : throw new InvalidArgumentException("Campo {$field} vazio ou longo demais.");
        $list = fn (mixed $value, string $field): array => is_array($value) && array_is_list($value)
            ? array_map(fn ($item) => $text($item, 600, $field), $value)
            : throw new InvalidArgumentException("Campo {$field} não é uma lista.");

        $resumo = $list($data['resumo'] ?? null, 'resumo');

        if (count($resumo) < 1 || count($resumo) > 6) {
            throw new InvalidArgumentException('O resumo precisa ter de um a seis tópicos.');
        }

        $secoes = [];

        foreach (is_array($data['secoes'] ?? null) ? $data['secoes'] : [] as $secao) {
            $secoes[] = ['tema' => $text($secao['tema'] ?? null, 80, 'secoes.tema'), 'itens' => $list($secao['itens'] ?? null, 'secoes.itens')];
        }

        $article = new self(
            titulo: $text($data['titulo'] ?? null, 120, 'titulo'),
            linhaFina: $text($data['linha_fina'] ?? null, 240, 'linha_fina'),
            resumo: $resumo,
            secoes: $secoes,
            tambem: $list($data['tambem'] ?? [], 'tambem'),
            omitidos: array_values(array_map(
                fn ($o) => ['documento' => $text($o['documento'] ?? null, 200, 'omitidos'), 'motivo' => $text($o['motivo'] ?? null, 400, 'omitidos')],
                is_array($data['omitidos'] ?? null) ? $data['omitidos'] : [],
            )),
        );

        if ($knownAuthors !== []) {
            $article->checkAuthors($knownAuthors);
        }

        return $article;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'titulo' => $this->titulo,
            'linha_fina' => $this->linhaFina,
            'resumo' => $this->resumo,
            'secoes' => $this->secoes,
            'tambem' => $this->tambem,
            'omitidos' => $this->omitidos,
        ];
    }

    /**
     * Cada pedido termina com o autor entre parênteses: todos precisam ser conhecidos.
     *
     * @param  list<string>  $known
     */
    private function checkAuthors(array $known): void
    {
        foreach ($this->secoes as $secao) {
            foreach ($secao['itens'] as $item) {
                if (! preg_match('~\(([^()]+)\)\s*\.?$~u', $item, $m)) {
                    continue;
                }

                foreach (preg_split('~\s*(?:,|\se\s)\s*~u', $m[1]) ?: [] as $name) {
                    if (! in_array(trim($name), $known, true)) {
                        throw new InvalidArgumentException("Autor desconhecido no texto: {$name}");
                    }
                }
            }
        }
    }
}
