<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Archivo rechazado por la lista blanca de tipos o por tamaño. El mensaje
 * es apto para mostrarse al usuario.
 */
class RejectedUploadException extends RuntimeException {}
