<?php

namespace App\Programas;

use App\Site\Timeline;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A notícia de um episódio escrita pelo Gemini (regras em
 * resources/prompts/programa-video.md), conferida antes de publicar:
 * tamanhos, "momentos" dentro do vídeo, nenhum dado de
 * contato ou pagamento no texto e o dia da semana certo.
 */
final class VideoStory
{
    private const WEEKDAYS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /**
     * Esquema da resposta (response_format da API Interactions).
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $text = ['type' => 'string'];
        $list = fn (array $properties) => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties)]];

        return [
            'type' => 'object',
            'properties' => [
                'titulo' => $text,
                'linha_fina' => $text,
                'lide' => $text,
                'blocos' => $list(['intertitulo' => $text, 'paragrafos' => ['type' => 'array', 'items' => $text]]),
                'fechamento' => $text,
                'participantes' => $list(['nome' => $text, 'papel' => $text]),
                'momentos' => $list(['inicio' => $text, 'assunto' => $text]),
                'omitidos' => ['type' => 'array', 'items' => $text],
            ],
            'required' => ['titulo', 'linha_fina', 'lide', 'blocos', 'fechamento', 'participantes', 'momentos', 'omitidos'],
        ];
    }

    /**
     * Confere a resposta e devolve a notícia pronta para gravar (cada momento ganha "segundos").
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function check(array $data, int $duration, CarbonImmutable $date): array
    {
        $article = [
            'titulo' => self::text($data, 'titulo', 120),
            'linha_fina' => self::text($data, 'linha_fina', 240),
            'lide' => self::text($data, 'lide', 1200),
            'blocos' => self::blocks($data['blocos'] ?? null),
            'fechamento' => self::text($data, 'fechamento', 1000, required: false),
            'participantes' => self::people($data['participantes'] ?? null),
            'momentos' => self::moments($data['momentos'] ?? null, $duration),
            'omitidos' => array_values(array_filter((array) ($data['omitidos'] ?? []), 'is_string')),
        ];

        $body = implode("\n", [
            $article['titulo'], $article['linha_fina'], $article['lide'], $article['fechamento'],
            ...array_merge(...array_map(fn (array $b) => [$b['intertitulo'], ...$b['paragrafos']], $article['blocos'])),
        ]);

        self::checkContacts($body);
        self::checkWeekday($body, $date);

        return $article;
    }

    /**
     * Dados de entrada do episódio para o Gemini, com a data já por extenso:
     * o modelo errou o dia da semana na prova de conceito.
     *
     * @return array<string, string>
     */
    public static function input(Program $program, string $title, CarbonImmutable $date, int $seconds): array
    {
        return [
            'programa' => $program->name,
            'emissora' => $program->station,
            'titulo' => $title,
            // "sexta-feira, 6 de novembro de 2026"
            'data' => self::WEEKDAYS[$date->dayOfWeek].($date->isWeekend() ? '' : '-feira').', '.$date->day.' de '.Timeline::monthName($date).' de '.$date->year,
            'duracao' => self::clock($seconds),
        ];
    }

    /**
     * 5351 → "1:29:11"; 2296 → "38:16".
     */
    public static function clock(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function text(array $data, string $field, int $max, bool $required = true): string
    {
        $value = trim((string) (is_string($data[$field] ?? null) ? $data[$field] : ''));

        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Campo {$field} vazio ou longo demais.");
        }

        return $value;
    }

    /**
     * @return list<array{intertitulo: string, paragrafos: list<string>}>
     */
    private static function blocks(mixed $blocks): array
    {
        if (! is_array($blocks) || count($blocks) < 1 || count($blocks) > 5) {
            throw new InvalidArgumentException('A notícia precisa ter de um a cinco blocos.');
        }

        $result = [];

        foreach ($blocks as $block) {
            $paragraphs = array_values(array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : '', (array) ($block['paragrafos'] ?? [])), fn (string $p) => $p !== ''));

            if (count($paragraphs) < 1 || count($paragraphs) > 4) {
                throw new InvalidArgumentException('Cada bloco precisa ter de um a quatro parágrafos.');
            }

            $result[] = ['intertitulo' => trim((string) ($block['intertitulo'] ?? '')), 'paragrafos' => $paragraphs];
        }

        return $result;
    }

    /**
     * @return list<array{nome: string, papel: string}>
     */
    private static function people(mixed $people): array
    {
        $result = [];

        foreach (is_array($people) ? $people : [] as $person) {
            $name = trim((string) ($person['nome'] ?? ''));
            $role = trim((string) ($person['papel'] ?? ''));

            if ($name !== '' || $role !== '') {
                $result[] = ['nome' => $name, 'papel' => $role];
            }
        }

        return $result;
    }

    /**
     * Momentos: onde cada assunto da notícia é dito no vídeo, para o leitor
     * conferir. Minutos válidos e dentro do vídeo; mostrados em ordem de tempo.
     *
     * @return list<array{inicio: string, segundos: int, assunto: string}>
     */
    private static function moments(mixed $moments, int $duration): array
    {
        if (! is_array($moments) || count($moments) < 1 || count($moments) > 12) {
            throw new InvalidArgumentException('A notícia precisa ter de um a doze momentos.');
        }

        $result = [];

        foreach ($moments as $moment) {
            $start = trim((string) ($moment['inicio'] ?? ''));
            $seconds = ChannelParser::seconds($start);
            $subject = trim((string) ($moment['assunto'] ?? ''));

            if ($seconds === null || $subject === '') {
                throw new InvalidArgumentException("Momento inválido: {$start}");
            }

            if ($seconds > $duration) {
                throw new InvalidArgumentException("Momento depois do fim do vídeo: {$start}");
            }

            $result[] = ['inicio' => $start, 'segundos' => $seconds, 'assunto' => $subject];
        }

        usort($result, fn (array $a, array $b) => $a['segundos'] <=> $b['segundos']);

        return $result;
    }

    /**
     * Telefone, e-mail, @perfil e endereço de site não entram: um erro de
     * transcrição mandaria dinheiro ou mensagens para a pessoa errada.
     */
    private static function checkContacts(string $body): void
    {
        $patterns = [
            'telefone ou chave' => '~\(?\b\d{2}\)?\s?9?\d{4}[-\s.]?\d{4}\b~u',
            'e-mail' => '~[\w.+-]+@[\w-]+\.[\w.]+~u',
            'perfil de rede social' => '~(?<![\w.])@\w{3,}~u',
            'endereço de site' => '~\bhttps?://|\bwww\.~iu',
        ];

        foreach ($patterns as $what => $pattern) {
            if (preg_match($pattern, $body, $m)) {
                throw new InvalidArgumentException("Dado de contato no texto ({$what}): {$m[0]}");
            }
        }
    }

    /**
     * "sexta-feira (6)" precisa bater com a data do episódio.
     */
    private static function checkWeekday(string $body, CarbonImmutable $date): void
    {
        preg_match_all('~\b(segunda|terça|quarta|quinta|sexta|sábado|domingo)(?:-feira)?\s*\((\d{1,2})\)~iu', $body, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            if ((int) $m[2] === $date->day && mb_strtolower($m[1]) !== self::WEEKDAYS[$date->dayOfWeek]) {
                throw new InvalidArgumentException("Dia da semana errado no texto: {$m[0]}");
            }
        }
    }
}
