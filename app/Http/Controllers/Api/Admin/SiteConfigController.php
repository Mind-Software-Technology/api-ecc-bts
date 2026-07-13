<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteConfigResource;
use App\Models\SiteConfig;
use Illuminate\Http\Request;

class SiteConfigController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'brand_name' => 'sometimes|required|string|max:255',
            'logo_url' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'social_links' => 'sometimes|array',
            'nav_items' => 'sometimes|array',
        ]);

        $config = SiteConfig::firstOrFail();
        $config->update($data);

        return new SiteConfigResource($config);
    }
}
