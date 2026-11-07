<?php

namespace App\Camara;

use InvalidArgumentException;

/**
 * A notícia da sessão em texto corrido, como o Gemini escreve: lide, blocos
 * com intertítulo e fechamento (pirâmide invertida). O modelo fixo, sem IA,
 * continua usando Article, em lista.
 */
final class Story
{
    public const FORMAT = 'texto';

    /**
     * @param  list<array{intertitulo: string, paragrafos: list<string>}>  $blocos
     * @param  list<array{documento: string, motivo: string}>  $omitidos
     */
    public function __construct(
        public string $titulo,
        public string $linhaFina,
        public string $lide,
        public array $blocos,
        public string $fechamento = '',
        public array $omitidos = [],
    ) {}

    /**
     * Esquema JSON da resposta pedida ao Gemini.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'titulo' => ['type' => 'string', 'description' => 'Título da notícia, até 90 caracteres.'],
                'linha_fina' => ['type' => 'string', 'description' => 'Linha fina, até 160 caracteres.'],
                'lide' => ['type' => 'string', 'description' => 'Primeiro parágrafo: o fato mais importante da sessão.'],
                'blocos' => [
                    'type' => 'array',
                    'description' => 'De dois a quatro blocos, um por assunto.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'intertitulo' => ['type' => 'string', 'description' => 'Intertítulo curto, ou vazio.'],
                            'paragrafos' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['intertitulo', 'paragrafos'],
                    ],
                ],
                'fechamento' => ['type' => 'string', 'description' => 'Parágrafo final curto: presença, horário e próximos passos que constem nos dados.'],
                'omitidos' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['documento' => ['type' => 'string'], 'motivo' => ['type' => 'string']],
                        'required' => ['documento', 'motivo'],
                    ],
                ],
            ],
            'required' => ['titulo', 'linha_fina', 'lide', 'blocos', 'fechamento', 'omitidos'],
        ];
    }

    /**
     * Lê e confere a notícia: campos, tamanhos e cada vereador citado (nome
     * conhecido e cargo no gênero certo).
     *
     * @param  array<mixed>  $data
     * @param  array<string, string>  $councilors  nome curto => "vereador" ou "vereadora"
     */
    public static function fromArray(array $data, array $councilors = []): self
    {
        $text = fn (mixed $value, int $max, string $field, bool $empty = false): string => is_string($value) && ($empty || trim($value) !== '') && mb_strlen($value) <= $max
            ? trim($value)
            : throw new InvalidArgumentException("Campo {$field} vazio ou longo demais.");

        $blocos = [];

        foreach (is_array($data['blocos'] ?? null) ? $data['blocos'] : [] as $bloco) {
            $paragrafos = is_array($bloco['paragrafos'] ?? null) && array_is_list($bloco['paragrafos']) ? $bloco['paragrafos'] : [];

            if ($paragrafos === [] || count($paragrafos) > 4) {
                throw new InvalidArgumentException('Cada bloco precisa ter de um a quatro parágrafos.');
            }

            $blocos[] = [
                'intertitulo' => $text($bloco['intertitulo'] ?? '', 80, 'intertitulo', true),
                'paragrafos' => array_map(fn ($p) => $text($p, 1000, 'paragrafos'), $paragrafos),
            ];
        }

        if ($blocos === [] || count($blocos) > 5) {
            throw new InvalidArgumentException('A notícia precisa ter de um a cinco blocos.');
        }

        $story = new self(
            titulo: $text($data['titulo'] ?? null, 120, 'titulo'),
            linhaFina: $text($data['linha_fina'] ?? null, 240, 'linha_fina'),
            lide: $text($data['lide'] ?? null, 1000, 'lide'),
            blocos: $blocos,
            fechamento: $text($data['fechamento'] ?? '', 700, 'fechamento', true),
            omitidos: array_values(array_map(
                fn ($o) => ['documento' => $text($o['documento'] ?? null, 200, 'omitidos'), 'motivo' => $text($o['motivo'] ?? null, 400, 'omitidos')],
                is_array($data['omitidos'] ?? null) ? $data['omitidos'] : [],
            )),
        );

        if ($councilors !== []) {
            $story->checkCouncilors($councilors);
        }

        return $story;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'titulo' => $this->titulo,
            'linha_fina' => $this->linhaFina,
            'lide' => $this->lide,
            'blocos' => $this->blocos,
            'fechamento' => $this->fechamento,
            'omitidos' => $this->omitidos,
        ];
    }

    /**
     * Todo o texto da notícia, para conferir os nomes.
     */
    public function text(): string
    {
        $parts = [$this->titulo, $this->linhaFina, $this->lide, $this->fechamento];

        foreach ($this->blocos as $bloco) {
            array_push($parts, $bloco['intertitulo'], ...$bloco['paragrafos']);
        }

        return implode("\n", $parts);
    }

    /**
     * Depois de "vereador"/"vereadora" tem de vir alguém da lista, no gênero certo.
     *
     * @param  array<string, string>  $councilors
     */
    private function checkCouncilors(array $councilors): void
    {
        preg_match_all('~\b([Vv]ereadora?)\s+(\p{Lu}[\p{L}\'’-]*(?:\s+(?:d[aeo]s?\s+)?\p{Lu}[\p{L}\'’-]*){0,3})~u', $this->text(), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $role, $name]) {
            $known = null;

            foreach ($councilors as $councilor => $title) {
                if (str_starts_with($name, $councilor)) {
                    $known = $councilor;
                    break;
                }
            }

            if ($known === null) {
                throw new InvalidArgumentException("Vereador desconhecido no texto: {$name}");
            }

            if (mb_strtolower($role) !== $councilors[$known]) {
                throw new InvalidArgumentException("Cargo no gênero errado no texto: {$role} {$known}");
            }
        }
    }
}
