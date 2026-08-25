<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'brand_name', 'logo_url', 'logo_path', 'contact_email', 'contact_phone', 'address',
    'bank_accounts', 'payment_method_mode', 'social_links', 'nav_items', 'hero',
])]
class SiteConfig extends Model
{
    protected function casts(): array
    {
        return [
            'bank_accounts' => 'array',
            'social_links' => 'array',
            'nav_items' => 'array',
            'hero' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SiteConfig $config) {
            if ($config->isDirty('logo_path') && $config->getOriginal('logo_path')) {
                Storage::disk('public')->delete($config->getOriginal('logo_path'));
            }
        });
    }

    public function midtransEnabled(): bool
    {
        return in_array($this->payment_method_mode, ['midtrans', 'both']);
    }

    public function manualEnabled(): bool
    {
        return in_array($this->payment_method_mode, ['manual', 'both']);
    }
}
