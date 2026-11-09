<?php

namespace App\Services\Storage;

use League\Flysystem\FilesystemException;
use RuntimeException;

/**
 * El NAS no está disponible (sin montaje, montaje incorrecto o sin red).
 * Se lanza ANTES de tocar el sistema de archivos para que una escritura
 * nunca acabe en el directorio vacío del servidor.
 */
class SynologyUnavailableException extends RuntimeException implements FilesystemException {}
