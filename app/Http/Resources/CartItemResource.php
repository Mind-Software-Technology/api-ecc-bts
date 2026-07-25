<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'qty' => $this->qty,
            'price' => $this->service->price,
            'line_total' => $this->service->price * $this->qty,
        ];
    }
}
