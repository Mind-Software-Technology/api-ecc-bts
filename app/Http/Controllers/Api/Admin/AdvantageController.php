<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdvantageResource;
use App\Models\Advantage;
use Illuminate\Http\Request;

class AdvantageController extends Controller
{
    public function index()
    {
        $advantages = Advantage::orderBy('sort_order')->get();

        return ['data' => AdvantageResource::collection($advantages)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'icon' => 'required|string|max:60',
            'title' => 'required|string|max:160',
            'description' => 'required|string',
            'sort_order' => 'integer',
        ]);

        return new AdvantageResource(Advantage::create($data));
    }

    public function update(Request $request, Advantage $advantage)
    {
        $data = $request->validate([
            'icon' => 'sometimes|required|string|max:60',
            'title' => 'sometimes|required|string|max:160',
            'description' => 'sometimes|required|string',
            'sort_order' => 'sometimes|integer',
        ]);

        $advantage->update($data);

        return new AdvantageResource($advantage);
    }

    public function destroy(Advantage $advantage)
    {
        $advantage->delete();

        return response()->noContent();
    }
}
