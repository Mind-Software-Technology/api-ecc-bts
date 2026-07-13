<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('sort_order')->get();

        return ['data' => CategoryResource::collection($categories)];
    }

    public function show(string $slug)
    {
        $category = Category::where('slug', $slug)
            ->with(['services' => fn ($query) => $query->where('is_active', true)])
            ->firstOrFail();

        return new CategoryResource($category);
    }
}
