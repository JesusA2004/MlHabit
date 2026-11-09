<?php

namespace App\Services\Storage;

use RuntimeException;

/**
 * El NAS no respondió y el servidor no tiene espacio suficiente para
 * guardar el archivo como respaldo. La subida se rechaza sin perder nada.
 */
class FallbackSpaceException extends RuntimeException {}
