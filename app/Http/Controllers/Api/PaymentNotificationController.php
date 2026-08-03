<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Support\MidtransSignature;
use App\Support\PaymentStatusSync;
use Illuminate\Http\Request;

class PaymentNotificationController extends Controller
{
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

        PaymentStatusSync::apply($payment, $transactionStatus, $fraudStatus);

        return response()->json(['received' => true]);
    }
}
