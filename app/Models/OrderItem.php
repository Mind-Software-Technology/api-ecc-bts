<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'service_id', 'title_snapshot', 'price_snapshot', 'qty', 'line_total',
    'attachment_path', 'attachment_original_name',
    'result_path', 'result_original_name', 'result_delivered_at',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'result_delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
