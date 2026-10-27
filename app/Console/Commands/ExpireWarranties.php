<?php

namespace App\Console\Commands;

use App\Services\Operations\WarrantyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('warranties:expire')]
#[Description('Marca como vencidas las garantías activas cuya vigencia ya terminó.')]
class ExpireWarranties extends Command
{
    public function handle(WarrantyService $warranties): int
    {
        $count = $warranties->expireDue();
        $this->info("Garantías vencidas: {$count}");

        return self::SUCCESS;
    }
}
