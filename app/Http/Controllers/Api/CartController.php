<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithCart;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use RespondsWithCart;

    public function show(Request $request)
    {
        return $this->cartResponse(Cart::forRequest($request));
    }

    public function clear(Request $request)
    {
        $cart = Cart::forRequest($request);
        $cart?->items()->delete();

        return $this->cartResponse($cart);
    }
}
