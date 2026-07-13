<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteConfigResource;
use App\Models\SiteConfig;

class SiteConfigController extends Controller
{
    public function show()
    {
        return new SiteConfigResource(SiteConfig::firstOrFail());
    }
}
