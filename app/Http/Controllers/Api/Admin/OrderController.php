<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Notifications\OrderResultReady;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Revenue + order count per month, last 12 months (including the
     * current one), for the dashboard chart. Grouped in PHP rather than
     * SQL DATE_FORMAT/strftime so it works the same on MySQL (prod) and
     * SQLite (tests).
     */
    public function revenue()
    {
        $since = now()->subMonths(11)->startOfMonth();

        $byMonth = Order::where('status', 'paid')
            ->where('created_at', '>=', $since)
            ->get(['total', 'created_at'])
            ->groupBy(fn ($order) => $order->created_at->format('Y-m'));

        $data = collect(range(11, 0))->map(function ($monthsAgo) use ($byMonth) {
            $key = now()->subMonths($monthsAgo)->format('Y-m');
            $group = $byMonth->get($key, collect());

            return [
                'month' => $key,
                'revenue' => (int) $group->sum('total'),
                'orders' => $group->count(),
            ];
        });

        return ['data' => $data->values()];
    }

    public function show(string $order_no)
    {
        $order = Order::where('order_no', $order_no)
            ->with(['items', 'payments'])
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function uploadResult(Request $request, string $order_no, int $item)
    {
        $order = Order::where('order_no', $order_no)->with('items')->firstOrFail();
        $orderItem = $order->items->firstWhere('id', $item);
        abort_if(! $orderItem, 404);

        $request->validate([
            'result' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $isRevision = $orderItem->result_path !== null;

        if ($isRevision) {
            Storage::disk('local')->delete($orderItem->result_path);
        }

        $file = $request->file('result');
        $orderItem->update([
            'result_path' => $file->store('order-results', 'local'),
            'result_original_name' => $file->getClientOriginalName(),
            'result_delivered_at' => now(),
        ]);

        Notification::route('mail', $order->guest_email)->notify(new OrderResultReady($order, $orderItem, $isRevision));

        return new OrderItemResource($orderItem);
    }
}
