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
            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => $this->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'original_name' => $a->original_name,
                ]),
                [],
            ),
            'attachments_count' => $this->whenLoaded('attachments', fn () => $this->attachments->count(), fn () => $this->attachment_path !== null ? 1 : 0),
            'has_result' => $this->result_path !== null,
            'result_original_name' => $this->result_original_name,
            'result_delivered_at' => $this->result_delivered_at?->toIso8601String(),
            'results' => $this->whenLoaded(
                'results',
                fn () => $this->results->map(fn ($r) => [
                    'id' => $r->id,
                    'original_name' => $r->original_name,
                ]),
                [],
            ),
            'results_count' => $this->whenLoaded('results', fn () => $this->results->count(), fn () => $this->result_path !== null ? 1 : 0),
        ];
    }
}
