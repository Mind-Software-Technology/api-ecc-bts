<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Support\MidtransSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentNotificationController extends Controller
{
    private const TERMINAL_STATUSES = ['settlement', 'capture', 'deny', 'cancel', 'expire', 'refund', 'partial_refund'];

    public function handle(Request $request)
    {
        $payload = $request->all();

        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');
        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = $payload['fraud_status'] ?? null;

        $valid = MidtransSignature::isValid(
            $orderId, $statusCode, $grossAmount, $signatureKey,
            (string) config('services.midtrans.server_key'),
        );

        $payment = Payment::where('midtrans_order_id', $orderId)->first();

        // Always log the notification, valid or not — audit trail regardless of outcome.
        PaymentNotification::create([
            'payment_id' => $payment?->id,
            'midtrans_order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'signature_valid' => $valid,
            'raw_payload' => $payload,
            'received_at' => now(),
        ]);

        abort_unless($valid, 403);
        abort_unless($payment, 404);

        DB::transaction(function () use ($payment, $transactionStatus, $fraudStatus) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Already-final status — ignore a late/duplicate/out-of-order webhook.
            if (in_array($locked->transaction_status, self::TERMINAL_STATUSES)) {
                return;
            }

            $orderStatus = match (true) {
                $transactionStatus === 'settlement' => 'paid',
                $transactionStatus === 'capture' && $fraudStatus === 'accept' => 'paid',
                $transactionStatus === 'capture' => 'failed',
                $transactionStatus === 'deny' => 'failed',
                $transactionStatus === 'cancel' => 'cancelled',
                $transactionStatus === 'expire' => 'expired',
                default => null,
            };

            $locked->update([
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'paid_at' => $orderStatus === 'paid' ? ($locked->paid_at ?? now()) : $locked->paid_at,
            ]);

            if ($orderStatus) {
                $locked->order()->update(['status' => $orderStatus]);
            }
        });

        return response()->json(['received' => true]);
    }
}
