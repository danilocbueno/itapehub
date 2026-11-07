<?php

namespace App\Camara;

use RuntimeException;

/**
 * Falha passageira do Gemini (sobrecarga, cota, tempo esgotado): vale tentar de novo.
 */
final class TemporaryFailure extends RuntimeException {}
