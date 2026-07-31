<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

#[Fillable([
    'order_no', 'user_id', 'guest_name', 'guest_email', 'guest_phone',
    'status', 'subtotal', 'total',
])]
class Order extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Find an order by order_no and enforce ownership: only the logged-in
     * user who placed it may access it (order routes require auth:sanctum —
     * every order now has a user_id, guest checkout no longer exists).
     * Mismatch -> 404, not 403, so existence isn't leaked.
     */
    public static function findAccessibleOrFail(string $orderNo, Request $request): self
    {
        $order = static::where('order_no', $orderNo)->with(['items', 'payments'])->firstOrFail();

        if ($request->user()?->id !== $order->user_id) {
            throw (new ModelNotFoundException)->setModel(self::class);
        }

        return $order;
    }
}
