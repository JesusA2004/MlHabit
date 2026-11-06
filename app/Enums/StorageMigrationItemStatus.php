<?php

namespace App\Enums;

enum StorageMigrationItemStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
