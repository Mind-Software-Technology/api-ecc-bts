<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'brand_name', 'logo_url', 'contact_email', 'contact_phone', 'address',
    'bank_name', 'bank_account_number', 'bank_account_holder', 'social_links', 'nav_items',
])]
class SiteConfig extends Model
{
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'nav_items' => 'array',
        ];
    }
}
