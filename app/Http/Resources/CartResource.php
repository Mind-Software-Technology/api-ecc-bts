<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items');
        $total = $items instanceof \Illuminate\Support\Collection
            ? $items->sum(fn ($item) => $item->service->price * $item->qty)
            : 0;

        return [
            'session_id' => $this->session_id,
            'items' => CartItemResource::collection($items),
            'subtotal' => $total,
            'total' => $total,
        ];
    }
}
