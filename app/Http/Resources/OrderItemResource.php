<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'title_snapshot' => $this->title_snapshot,
            'price_snapshot' => $this->price_snapshot,
            'qty' => $this->qty,
            'line_total' => $this->line_total,
            'requires_attachment' => (bool) ($this->service?->requires_attachment ?? false),
            'has_attachment' => $this->attachment_path !== null,
            'attachment_original_name' => $this->attachment_original_name,
            'has_result' => $this->result_path !== null,
            'result_original_name' => $this->result_original_name,
            'result_delivered_at' => $this->result_delivered_at?->toIso8601String(),
        ];
    }
}
