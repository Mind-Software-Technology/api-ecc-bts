<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $cart = Cart::forRequest($request);
        abort_if(! $cart || $cart->items()->count() === 0, 422, 'Cart is empty.');

        $user = $request->user();
        $data = $request->validate([
            'guest_name' => $user ? 'nullable|string|max:255' : 'required|string|max:255',
            'guest_email' => $user ? 'nullable|email|max:255' : 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:30',
        ]);

        $order = DB::transaction(function () use ($cart, $user, $data) {
            $order = Order::create([
                'order_no' => $this->generateOrderNo(),
                'user_id' => $user?->id,
                'guest_name' => $user ? ($data['guest_name'] ?? $user->name) : $data['guest_name'],
                'guest_email' => $user ? ($data['guest_email'] ?? $user->email) : $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? $user?->phone,
                'status' => 'pending',
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;
            foreach ($cart->items()->with('service')->get() as $item) {
                $lineTotal = $item->service->price * $item->qty;
                $subtotal += $lineTotal;

                $order->items()->create([
                    'service_id' => $item->service_id,
                    'title_snapshot' => $item->service->title,
                    'price_snapshot' => $item->service->price,
                    'qty' => $item->qty,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
            $cart->items()->delete();

            return $order;
        });

        return new OrderResource($order->load('items'));
    }

    public function show(Request $request, string $order_no)
    {
        return new OrderResource(Order::findAccessibleOrFail($order_no, $request));
    }

    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 20) ?: 20, 100);
        $page = (int) $request->query('page', 1);

        if ($user = $request->user()) {
            $query = Order::where('user_id', $user->id);
        } else {
            $email = $request->validate(['email' => 'required|email'])['email'];
            $query = Order::whereNull('user_id')->where('guest_email', $email);
        }

        $orders = $query->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'page' => $orders->currentPage(),
                'limit' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ];
    }

    /**
     * INV-{Ymd}-{4 random digits}; retry on the rare collision.
     */
    private function generateOrderNo(): string
    {
        do {
            $orderNo = 'INV-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::where('order_no', $orderNo)->exists());

        return $orderNo;
    }
}
