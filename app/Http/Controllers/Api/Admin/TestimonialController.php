<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::orderBy('sort_order')->get();

        return ['data' => TestimonialResource::collection($testimonials)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'text' => 'required|string',
            'rating' => 'required|numeric|min:0|max:5',
            'sort_order' => 'integer',
        ]);

        return new TestimonialResource(Testimonial::create($data));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'role' => 'sometimes|required|string|max:255',
            'text' => 'sometimes|required|string',
            'rating' => 'sometimes|required|numeric|min:0|max:5',
            'sort_order' => 'sometimes|integer',
        ]);

        $testimonial->update($data);

        return new TestimonialResource($testimonial);
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return response()->noContent();
    }
}
