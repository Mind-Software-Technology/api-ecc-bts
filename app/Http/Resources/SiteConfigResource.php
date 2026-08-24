<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'brand_name' => $this->brand_name,
            'logo_url' => $this->logo_url,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'bank_accounts' => $this->bank_accounts ?? [],
            'payment_method_mode' => $this->payment_method_mode,
            'social_links' => $this->social_links,
            'nav_items' => $this->nav_items,
        ];
    }
}
