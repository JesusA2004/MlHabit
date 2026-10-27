<?php

namespace App\Exceptions;

/**
 * Se intentó completar una sesión de subida por fragmentos antes de que
 * todos los bytes esperados llegaran, o el checksum enviado por el cliente
 * no coincide con el archivo ensamblado.
 */
class IncompleteUploadException extends \RuntimeException {}
