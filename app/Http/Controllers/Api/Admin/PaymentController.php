<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 20) ?: 20, 100);

        $payments = Payment::query()
            ->when($request->query('status'), fn ($query, $status) => $query->where('transaction_status', $status))
            ->latest()
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return [
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'page' => $payments->currentPage(),
                'limit' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ];
    }

    public function show(Payment $payment)
    {
        return new PaymentResource($payment);
    }
}
