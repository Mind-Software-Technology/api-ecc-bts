<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('sort_order')->get();

        return ['data' => CategoryResource::collection($categories)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => 'required|string|max:60|unique:categories,slug',
            'title' => 'required|string|max:120',
            'short_desc' => 'required|string|max:160',
            'description' => 'required|string',
            'icon' => 'required|string|max:60',
            'accent' => 'required|string|max:20',
            'sort_order' => 'integer',
        ]);

        return new CategoryResource(Category::create($data));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'slug' => 'sometimes|required|string|max:60|unique:categories,slug,'.$category->id,
            'title' => 'sometimes|required|string|max:120',
            'short_desc' => 'sometimes|required|string|max:160',
            'description' => 'sometimes|required|string',
            'icon' => 'sometimes|required|string|max:60',
            'accent' => 'sometimes|required|string|max:20',
            'sort_order' => 'sometimes|integer',
        ]);

        $category->update($data);

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }
}
