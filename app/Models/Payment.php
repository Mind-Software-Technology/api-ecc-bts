<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_id', 'midtrans_order_id', 'transaction_id', 'payment_type', 'channel_detail',
    'gross_amount', 'transaction_status', 'fraud_status', 'va_number', 'qr_url',
    'deeplink_url', 'payment_code', 'expiry_time', 'paid_at', 'raw_response',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'expiry_time' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }
}
