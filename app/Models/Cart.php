<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Resolve the cart belonging to the current request: by user_id when
     * logged in (X-Session-Id is ignored in that case), otherwise by the
     * guest session_id header. $create=false never creates a row (used by
     * read-only endpoints so an empty visit doesn't litter the carts table).
     */
    public static function forRequest(Request $request, bool $create = false): ?self
    {
        if ($user = $request->user()) {
            return $create
                ? static::firstOrCreate(['user_id' => $user->id], ['owner_type' => 'user'])
                : static::where('user_id', $user->id)->first();
        }

        $sessionId = $request->header('X-Session-Id');
        $cart = $sessionId
            ? static::where('owner_type', 'guest')->where('session_id', $sessionId)->first()
            : null;

        if ($cart || ! $create) {
            return $cart;
        }

        return static::create(['owner_type' => 'guest', 'session_id' => $sessionId ?: Str::random(40)]);
    }
}
