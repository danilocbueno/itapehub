<?php

// Prova de conceito: manda um episódio do Telescópio (YouTube) ao Gemini e imprime a notícia.
// Uso (na raiz do projeto): set -a; . ./.env; set +a; php docs/telescopio-poc/poc.php [videoId] [titulo] [AAAA-MM-DD] [modelo] [agentic|static]

$key = getenv('GEMINI_API_KEY') ?: exit("Defina GEMINI_API_KEY.\n");
$video = $argv[1] ?? 'Wsw9YeliCdY';
$title = $argv[2] ?? 'Telescópio - 06/11/2026 - Ação voluntária - Entrega do Amor';
$date = $argv[3] ?? '2026-11-06';
$model = $argv[4] ?? 'gemini-3.8-flash';
$processing = $argv[5] ?? 'agentic';
$base = 'https://generativelanguage.googleapis.com/v1beta/interactions';

$text = ['type' => 'string'];
$schema = [
    'type' => 'object',
    'properties' => [
        'titulo' => $text,
        'linha_fina' => $text,
        'lide' => $text,
        'blocos' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'intertitulo' => $text,
            'paragrafos' => ['type' => 'array', 'items' => $text],
        ], 'required' => ['intertitulo', 'paragrafos']]],
        'fechamento' => $text,
        'participantes' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'nome' => $text, 'papel' => $text,
        ], 'required' => ['nome', 'papel']]],
        'momentos' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'inicio' => $text, 'assunto' => $text,
        ], 'required' => ['inicio', 'assunto']]],
        'omitidos' => ['type' => 'array', 'items' => $text],
    ],
    'required' => ['titulo', 'linha_fina', 'lide', 'blocos', 'fechamento', 'participantes', 'momentos', 'omitidos'],
];

function call(string $method, string $url, string $key, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['x-goog-api-key: '.$key, 'Api-Revision: 2026-05-20', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($raw === false || $status >= 400) {
        exit("Erro HTTP {$status}: ".($raw ?: curl_error($ch))."\n");
    }

    return json_decode($raw, true);
}

$started = microtime(true);
$interaction = call('POST', $base, $key, [
    'model' => $model,
    'system_instruction' => file_get_contents(__DIR__.'/prompt.md'),
    'input' => [
        ['type' => 'video', 'uri' => "https://www.youtube.com/watch?v={$video}", 'processing' => $processing],
        ['type' => 'text', 'text' => json_encode(['programa' => 'Telescópio', 'emissora' => 'TV Itapê', 'titulo' => $title, 'data' => $date], JSON_UNESCAPED_UNICODE)],
    ],
    'response_format' => ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema],
    'generation_config' => ['thinking_level' => 'high'],
    'background' => true,
]);

echo "Interação {$interaction['id']} ({$model}, {$processing}). Aguardando";
while (in_array($interaction['status'] ?? '', ['in_progress', 'queued', 'pending'], true)) {
    sleep(10);
    echo '.';
    $interaction = call('GET', "{$base}/{$interaction['id']}", $key);
}
printf("\nStatus: %s em %ds\n", $interaction['status'] ?? '?', microtime(true) - $started);

file_put_contents(__DIR__."/resposta-{$video}.json", json_encode($interaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$out = '';
foreach ($interaction['steps'] ?? [] as $step) {
    if (($step['type'] ?? '') === 'model_output') {
        foreach ($step['content'] ?? [] as $part) {
            $out .= $part['text'] ?? '';
        }
    }
}
$news = json_decode($out, true) ?: exit("Resposta sem JSON. Veja resposta-{$video}.json\n");
file_put_contents(__DIR__."/noticia-{$video}.json", json_encode($news, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n# {$news['titulo']}\n{$news['linha_fina']}\n\n{$news['lide']}\n";
foreach ($news['blocos'] as $b) {
    echo "\n## {$b['intertitulo']}\n".implode("\n\n", $b['paragrafos'])."\n";
}
echo "\n{$news['fechamento']}\n\nParticipantes:\n";
foreach ($news['participantes'] as $p) {
    echo '- '.($p['nome'] ?: '(sem nome)')." — {$p['papel']}\n";
}
echo "\nMomentos:\n";
foreach ($news['momentos'] as $m) {
    echo "- {$m['inicio']} {$m['assunto']}\n";
}
echo "\nOmitidos: ".(implode('; ', $news['omitidos']) ?: '—')."\n";
echo "\nUso: ".json_encode($interaction['usage'] ?? null)."\n";
