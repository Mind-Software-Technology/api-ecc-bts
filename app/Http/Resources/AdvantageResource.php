<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvantageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'icon' => $this->icon,
            'title' => $this->title,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
        ];
    }
}
