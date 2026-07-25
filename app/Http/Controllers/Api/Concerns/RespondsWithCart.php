<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;

trait RespondsWithCart
{
    /**
     * Every cart response echoes X-Session-Id so a guest client can persist
     * it (it's only ever assigned by the backend, never accepted from the body).
     */
    protected function cartResponse(?Cart $cart): JsonResponse
    {
        if (! $cart) {
            return response()->json(['session_id' => null, 'items' => [], 'subtotal' => 0, 'total' => 0]);
        }

        $cart->load('items.service');

        $response = (new CartResource($cart))->response();

        if ($cart->session_id) {
            $response->header('X-Session-Id', $cart->session_id);
        }

        return $response;
    }
}
