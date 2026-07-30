<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 12) ?: 12, 100);

        $services = Service::query()
            ->where('is_active', true)
            ->when($request->query('category'), function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->when($request->query('q'), function ($query, $term) {
                $query->where('title', 'like', "%{$term}%");
            })
            ->when($request->query('sort'), function ($query, $sort) {
                match ($sort) {
                    'price_asc' => $query->orderBy('price'),
                    'price_desc' => $query->orderByDesc('price'),
                    'rating' => $query->orderByDesc('rating'),
                    default => $query->orderBy('sort_order')->orderBy('id'),
                };
            }, function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            })
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return [
            'data' => ServiceResource::collection($services->items()),
            'meta' => [
                'page' => $services->currentPage(),
                'limit' => $services->perPage(),
                'total' => $services->total(),
            ],
        ];
    }

    public function show(string $slug)
    {
        $service = Service::where('slug', $slug)
            ->where('is_active', true)
            ->with('category')
            ->firstOrFail();

        return new ServiceResource($service);
    }
}
