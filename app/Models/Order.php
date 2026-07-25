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
     * Find an order by order_no and enforce ownership: a logged-in user
     * only sees their own orders; a guest must pass ?email= matching
     * guest_email (orders has no session_id column to check against
     * instead). Mismatch -> 404, not 403, so existence isn't leaked.
     */
    public static function findAccessibleOrFail(string $orderNo, Request $request): self
    {
        $order = static::where('order_no', $orderNo)->with(['items', 'payments'])->firstOrFail();

        if ($order->user_id !== null) {
            if ($request->user()?->id !== $order->user_id) {
                throw (new ModelNotFoundException)->setModel(self::class);
            }
        } else {
            $email = $request->query('email');
            if (! $email || strcasecmp($email, (string) $order->guest_email) !== 0) {
                throw (new ModelNotFoundException)->setModel(self::class);
            }
        }

        return $order;
    }
}
