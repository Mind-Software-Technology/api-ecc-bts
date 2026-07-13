<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StatResource;
use App\Models\Stat;

class StatController extends Controller
{
    public function index()
    {
        $stats = Stat::orderBy('sort_order')->get();

        return ['data' => StatResource::collection($stats)];
    }
}
