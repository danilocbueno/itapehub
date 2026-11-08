<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site
    |--------------------------------------------------------------------------
    |
    | Variáveis de ambiente permitem trocar nome, endereço e estatísticas sem
    | mexer no código. No GitHub Actions, SITE_URL e SITE_BASE vêm do
    | actions/configure-pages.
    |
    */

    // "?:" porque variáveis não configuradas no GitHub chegam como string vazia.
    'site' => [
        'name' => env('SITE_NAME') ?: 'ItapêHub',
        'tagline' => env('SITE_TAGLINE') ?: 'Tudo o que acontece na cidade, num só lugar',
        // Com domínio próprio, o endereço é sempre https://domínio (o configure-pages
        // devolve http:// enquanto o "Enforce HTTPS" do GitHub Pages não está ligado).
        'url' => env('SITE_DOMAIN') ? 'https://'.env('SITE_DOMAIN') : rtrim((string) (env('SITE_URL') ?: 'http://localhost:8000'), '/'),
        'base' => rtrim((string) env('SITE_BASE'), '/'),
        'domain' => env('SITE_DOMAIN') ?: null,
        'contact' => env('SITE_CONTACT') ?: null,
        'goatcounter' => env('GOATCOUNTER_CODE') ?: null,
    ],

    'paths' => [
        'data' => env('ITAPE_DATA_PATH') ?: base_path('data'),
        'build' => env('ITAPE_BUILD_PATH') ?: base_path('build'),
    ],

    'http' => [
        'timeout' => 20,
        'connect_timeout' => 10,
        'concurrency' => 8,
        // Vários sites (ex.: Xeh) recusam clientes que não parecem navegadores.
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/130.0 Safari/537.36 ItapeHub/1.0 (+'.(env('SITE_URL') ?: 'https://github.com').')',
    ],

    'pipeline' => [
        // Itens mais antigos que isso são ignorados na coleta.
        'max_age_days' => 30,
        // Máximo de páginas de notícia baixadas por execução para completar dados.
        'enrich_limit' => 40,
        // Notícias salvas com título cortado reabertas por execução.
        'repair_limit' => 10,
        'summary_length' => 240,
        'cluster_window_hours' => 48,
    ],

    'home' => [
        'days' => 3,
        // Garante uma home cheia mesmo em períodos com poucas notícias.
        'min_clusters' => 30,
    ],

    'feed' => [
        'items' => 50,
    ],

    'agenda' => [
        // Quantos dias à frente a agenda mostra.
        'days' => 14,
    ],

    'theme_page' => [
        // Notícias mais recentes listadas em cada página de tema.
        'items' => 60,
    ],

    'weather' => [
        'latitude' => -23.59,
        'longitude' => -48.05,
        'ttl_minutes' => 55,
        'url' => 'https://api.open-meteo.com/v1/forecast',
    ],

    'camara' => [
        // Sistema de sessões da Câmara (SisCam): pauta, presença e votações.
        'url' => 'https://itapetininga.siscam.com.br',
        // Sessões mais antigas que isso não são buscadas nem atualizadas.
        'days' => 14,
        // Texto da notícia pelo Gemini (API Interactions). Sem chave, usa o modelo fixo.
        'gemini' => [
            'key' => env('GEMINI_API_KEY') ?: null,
            // Em ordem: se o primeiro estiver sobrecarregado, tenta o seguinte (ambos com cota gratuita).
            'models' => [env('GEMINI_MODEL') ?: 'gemini-3.8-flash', 'gemini-3.5-flash-lite'],
            'url' => 'https://generativelanguage.googleapis.com/v1beta/interactions',
            'revision' => '2026-05-20',
            'thinking_level' => 'high',
            // Com raciocínio alto, a resposta pode levar minutos. Pior caso por sessão:
            // modelos × (1 + retries) × timeout = 6 min, dentro dos 8 min do passo no workflow.
            'timeout' => 180,
            // Novas tentativas no mesmo modelo dentro da execução. Zero: a coleta roda de hora
            // em hora, e a execução seguinte já tenta de novo (até max_attempts).
            'retries' => 0,
            'retry_seconds' => 15,
            // Execuções em que uma notícia feita pelo modelo fixo volta a tentar o Gemini.
            'max_attempts' => 6,
        ],
    ],

    'alerts' => [
        'url' => 'https://apiprevmet3.inmet.gov.br/avisos/rss',
        // Nome da mesorregião do IBGE usada nos avisos do INMET.
        'area' => 'Itapetininga',
        'ttl_minutes' => 25,
    ],

];
