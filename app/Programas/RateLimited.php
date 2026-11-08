<?php

namespace App\Programas;

use RuntimeException;

/**
 * O Gemini recusou por cota (HTTP 429: tokens por minuto ou horas de vídeo do
 * YouTube por dia). Não conta como tentativa: o episódio nem foi avaliado.
 */
final class RateLimited extends RuntimeException {}
