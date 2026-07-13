<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'title' => $this->title,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'points' => $this->points,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'image_url' => $this->image_url,
            'image_alt' => $this->image_alt,
            'price' => $this->price,
            'rating' => (float) $this->rating,
            'reviews_count' => $this->reviews_count,
            'badge' => $this->badge,
        ];
    }
}
