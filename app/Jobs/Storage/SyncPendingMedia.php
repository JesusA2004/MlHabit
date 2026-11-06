<?php

namespace App\Jobs\Storage;

use App\Services\Storage\PendingMediaSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sube al NAS lo que quedó en el servidor mientras no respondía. Se
 * despacha al probar con éxito la conexión o al pulsar «Sincronizar ahora».
 */
class SyncPendingMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function handle(PendingMediaSync $sync): void
    {
        $sync->run((int) config('media.storage.sync_batch', 200));
    }
}
