<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithCart;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartItemController extends Controller
{
    use RespondsWithCart;

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'qty' => 'required|integer|min:1',
        ]);

        $cart = Cart::forRequest($request, create: true);

        $item = $cart->items()->firstOrNew(['service_id' => $data['service_id']]);
        $item->qty = ($item->exists ? $item->qty : 0) + $data['qty'];
        $item->save();

        return $this->cartResponse($cart);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['qty' => 'required|integer|min:1']);

        $cart = Cart::forRequest($request);
        abort_if(! $cart, 404);

        $cart->items()->findOrFail($id)->update(['qty' => $data['qty']]);

        return $this->cartResponse($cart);
    }

    public function destroy(Request $request, int $id)
    {
        $cart = Cart::forRequest($request);
        abort_if(! $cart, 404);

        $cart->items()->findOrFail($id)->delete();

        return $this->cartResponse($cart);
    }
}
