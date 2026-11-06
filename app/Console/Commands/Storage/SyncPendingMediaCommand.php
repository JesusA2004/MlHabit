<?php

namespace App\Console\Commands\Storage;

use App\Services\Storage\PendingMediaSync;
use Illuminate\Console\Command;

/**
 * Sube al NAS los archivos que se guardaron en el servidor mientras el NAS
 * no respondía. Programado cada 10 minutos (routes/console.php).
 */
class SyncPendingMediaCommand extends Command
{
    protected $signature = 'media:sync-pending {--limit=200 : Máximo de archivos por ejecución}';

    protected $description = 'Sube al Synology los archivos que quedaron en el servidor por falta de conexión';

    public function handle(PendingMediaSync $sync): int
    {
        $result = $sync->run(max(1, (int) $this->option('limit')));
        $this->info($result['message']);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
