<?php

namespace App\Console\Commands;

use App\Services\ChunkedUploadService;
use Illuminate\Console\Command;

class PruneExpiredUploadSessions extends Command
{
    protected $signature = 'uploads:prune';

    protected $description = 'Limpia sesiones de subida multimedia abandonadas y sus archivos temporales';

    public function handle(ChunkedUploadService $chunks): int
    {
        $count = $chunks->pruneExpired();
        $this->info("Sesiones de subida abandonadas eliminadas: {$count}");

        return self::SUCCESS;
    }
}
