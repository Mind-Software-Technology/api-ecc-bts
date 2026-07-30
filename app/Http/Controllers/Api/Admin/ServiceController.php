<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::with('category')->orderBy('sort_order')->orderBy('id')->get();

        return ['data' => ServiceResource::collection($services)];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return new ServiceResource(Service::create($data)->load('category'));
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate($this->rules(sometimes: true, ignoreId: $service->id));

        $service->update($data);

        return new ServiceResource($service->load('category'));
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false, ?int $ignoreId = null): array
    {
        $prefix = $sometimes ? 'sometimes|required|' : 'required|';

        return [
            'slug' => $prefix.'string|max:80|unique:services,slug'.($ignoreId ? ','.$ignoreId : ''),
            'category_id' => $prefix.'integer|exists:categories,id',
            'sort_order' => 'sometimes|integer|min:0',
            'title' => $prefix.'string|max:160',
            'tagline' => $prefix.'string|max:240',
            'description' => $prefix.'string',
            'points' => $prefix.'array',
            'icon' => $prefix.'string|max:60',
            'accent' => $prefix.'string|max:20',
            'image_url' => 'nullable|string|max:255',
            'image_alt' => 'nullable|string|max:160',
            'price' => $prefix.'integer|min:0',
            'rating' => 'sometimes|numeric|min:0|max:5',
            'reviews_count' => 'sometimes|integer|min:0',
            'badge' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
