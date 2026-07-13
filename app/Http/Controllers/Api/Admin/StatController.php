<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StatResource;
use App\Models\Stat;
use Illuminate\Http\Request;

class StatController extends Controller
{
    public function index()
    {
        $stats = Stat::orderBy('sort_order')->get();

        return ['data' => StatResource::collection($stats)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'value' => 'required|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'label' => 'required|string|max:120',
            'sort_order' => 'integer',
        ]);

        return new StatResource(Stat::create($data));
    }

    public function update(Request $request, Stat $stat)
    {
        $data = $request->validate([
            'value' => 'sometimes|required|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'label' => 'sometimes|required|string|max:120',
            'sort_order' => 'sometimes|integer',
        ]);

        $stat->update($data);

        return new StatResource($stat);
    }

    public function destroy(Stat $stat)
    {
        $stat->delete();

        return response()->noContent();
    }
}
