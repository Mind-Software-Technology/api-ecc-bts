<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'brand_name', 'logo_url', 'contact_email', 'contact_phone', 'address',
    'bank_accounts', 'payment_method_mode', 'social_links', 'nav_items',
])]
class SiteConfig extends Model
{
    protected function casts(): array
    {
        return [
            'bank_accounts' => 'array',
            'social_links' => 'array',
            'nav_items' => 'array',
        ];
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
