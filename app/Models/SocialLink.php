<?php

namespace App\Models;

use App\Concerns\FlushesPublicSite;
use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $platform
 * @property string|null $username
 * @property string $url
 * @property string|null $icon
 * @property bool $is_visible
 * @property bool $open_new_tab
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SocialLink extends Model
{
    /** @use HasFactory<SocialLinkFactory> */
    use FlushesPublicSite, HasFactory;

    protected $fillable = ['platform', 'username', 'url', 'icon', 'is_visible', 'open_new_tab', 'order'];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'open_new_tab' => 'boolean',
            'order' => 'integer',
        ];
    }

    /** @param Builder<SocialLink> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true)->orderBy('order');
    }
}
