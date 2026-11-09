<?php

namespace App\Programas;

use RuntimeException;

/**
 * O Gemini ainda não consegue ler o vídeo (HTTP 403, "The caller does not have
 * permission"): depois que uma live termina, o YouTube processa a gravação por
 * cerca de 12 horas. Não conta como tentativa; o episódio espera as próximas
 * execuções (até give_up_days depois de encontrado).
 */
final class NotReady extends RuntimeException {}
