<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdvantageResource;
use App\Models\Advantage;

class AdvantageController extends Controller
{
    public function index()
    {
        $advantages = Advantage::orderBy('sort_order')->get();

        return ['data' => AdvantageResource::collection($advantages)];
    }
}
