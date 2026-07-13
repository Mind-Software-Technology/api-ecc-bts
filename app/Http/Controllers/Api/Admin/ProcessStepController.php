<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessStepResource;
use App\Models\ProcessStep;
use Illuminate\Http\Request;

class ProcessStepController extends Controller
{
    public function index()
    {
        $steps = ProcessStep::orderBy('sort_order')->get();

        return ['data' => ProcessStepResource::collection($steps)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'step_number' => 'required|integer',
            'icon' => 'required|string|max:60',
            'title' => 'required|string|max:160',
            'description' => 'required|string',
            'sort_order' => 'integer',
        ]);

        return new ProcessStepResource(ProcessStep::create($data));
    }

    public function update(Request $request, ProcessStep $process_step)
    {
        $data = $request->validate([
            'step_number' => 'sometimes|required|integer',
            'icon' => 'sometimes|required|string|max:60',
            'title' => 'sometimes|required|string|max:160',
            'description' => 'sometimes|required|string',
            'sort_order' => 'sometimes|integer',
        ]);

        $process_step->update($data);

        return new ProcessStepResource($process_step);
    }

    public function destroy(ProcessStep $process_step)
    {
        $process_step->delete();

        return response()->noContent();
    }
}
