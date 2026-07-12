<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'midtrans_order_id', 'transaction_status', 'signature_valid', 'raw_payload', 'received_at'])]
class PaymentNotification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'raw_payload' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
