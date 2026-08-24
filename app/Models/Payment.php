<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'order_id', 'midtrans_order_id', 'transaction_id', 'payment_type', 'method', 'channel_detail',
    'gross_amount', 'transaction_status', 'fraud_status', 'va_number', 'qr_url',
    'deeplink_url', 'payment_code', 'expiry_time', 'paid_at', 'raw_response',
    'proof_path', 'proof_original_name', 'bank_account_snapshot', 'verified_by', 'verified_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'bank_account_snapshot' => 'array',
            'expiry_time' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Item pesanan milik order ini — dijangkau lewat order_id karena
     * order_items tidak punya kolom payment_id (satu order bisa punya
     * banyak payment, tapi item pesanannya cuma dimiliki order-nya).
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            Order::class,
            'id',
            'order_id',
            'order_id',
            'id',
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
