<?php

namespace App\Services\Storage;

use Illuminate\Filesystem\LocalFilesystemAdapter;

/**
 * Disco del NAS montado. Laravel solo sabe generar URLs de un disco local
 * si el adaptador es el local de Flysystem; aquí va envuelto en
 * MountGuardAdapter, así que la URL pública (/media/…) se arma desde la
 * configuración sin tocar el NAS (no requiere que esté conectado).
 */
class SynologyFilesystemAdapter extends LocalFilesystemAdapter
{
    /**
     * @param  string  $path
     */
    public function url($path): string
    {
        $base = rtrim((string) ($this->config['url'] ?? '/media'), '/');

        return $base.'/'.ltrim((string) $path, '/');
    }
}
