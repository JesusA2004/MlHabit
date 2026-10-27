<?php

namespace App\Exceptions;

/**
 * Un fragmento llegó fuera de orden o la sesión ya no acepta fragmentos
 * (expirada, completada o abortada). El cliente debe resincronizar su
 * posición consultando el estado de la sesión antes de reintentar.
 */
class InvalidChunkException extends \RuntimeException {}
