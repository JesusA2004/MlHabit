<?php

namespace App\Enums;

enum StorageProviderKey: string
{
    case Local = 'local';
    case Synology = 'synology';
}
