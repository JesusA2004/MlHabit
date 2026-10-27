<?php

namespace App\Models;

use App\Enums\StorageProviderKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property StorageProviderKey $key
 * @property string $label
 * @property bool $is_active
 * @property array<string, mixed>|null $config
 * @property Carbon|null $last_tested_at
 * @property array<string, mixed>|null $last_test_result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StorageProvider extends Model
{
    protected $fillable = ['key', 'label', 'is_active', 'config', 'last_tested_at', 'last_test_result'];

    /**
     * El config cifrado nunca debe viajar en una respuesta Inertia/JSON.
     */
    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'key' => StorageProviderKey::class,
            'is_active' => 'boolean',
            'config' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'last_test_result' => 'array',
        ];
    }

    public function configValue(string $field, mixed $default = null): mixed
    {
        return $this->config[$field] ?? $default;
    }
}
