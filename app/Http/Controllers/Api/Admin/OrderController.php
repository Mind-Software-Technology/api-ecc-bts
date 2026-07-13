<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 20) ?: 20, 100);

        $orders = Order::query()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return [
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'page' => $orders->currentPage(),
                'limit' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ];
    }

    public function show(string $order_no)
    {
        $order = Order::where('order_no', $order_no)
            ->with(['items', 'payments'])
            ->firstOrFail();

        return new OrderResource($order);
    }
}
