<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

#[Fillable(['owner_type', 'session_id', 'user_id'])]
class Cart extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Resolve the cart belonging to the current (always-authenticated —
     * cart routes require auth:sanctum) request's user. $create=false never
     * creates a row (used by read-only endpoints so an empty visit doesn't
     * litter the carts table).
     */
    public static function forRequest(Request $request, bool $create = false): ?self
    {
        $user = $request->user();

        return $create
            ? static::firstOrCreate(['user_id' => $user->id], ['owner_type' => 'user'])
            : static::where('user_id', $user->id)->first();
    }
}
