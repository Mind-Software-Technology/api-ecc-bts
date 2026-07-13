<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'short_desc' => $this->short_desc,
            'description' => $this->description,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'sort_order' => $this->sort_order,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}
