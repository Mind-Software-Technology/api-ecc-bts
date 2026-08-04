<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;

class TestimonialController extends Controller
{
    public function index()
    {
        $testimonials = Testimonial::with(['user:id,name,email', 'order:id,order_no'])
            ->orderBy('sort_order')->get();

        return ['data' => TestimonialResource::collection($testimonials)];
    }

    /**
     * Testimonials come from real customers now — admin can only moderate
     * visibility, not author or edit the content.
     */
    public function toggleActive(Testimonial $testimonial)
    {
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return new TestimonialResource($testimonial);
    }
}
