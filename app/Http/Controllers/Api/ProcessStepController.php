<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessStepResource;
use App\Models\ProcessStep;

class ProcessStepController extends Controller
{
    public function index()
    {
        $steps = ProcessStep::orderBy('sort_order')->get();

        return ['data' => ProcessStepResource::collection($steps)];
    }
}
