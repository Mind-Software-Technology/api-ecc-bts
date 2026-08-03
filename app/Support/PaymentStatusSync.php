<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Applies a Midtrans transaction_status to a Payment (+ its Order) idempotently.
 * Shared by the notification webhook and the polled status endpoint, so a
 * status change lands the same way regardless of which path delivers it.
 */
class PaymentStatusSync
{
    public const TERMINAL_STATUSES = ['settlement', 'capture', 'deny', 'cancel', 'expire', 'refund', 'partial_refund'];

    public static function apply(Payment $payment, string $transactionStatus, ?string $fraudStatus): void
    {
        DB::transaction(function () use ($payment, $transactionStatus, $fraudStatus) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            // Already-final status — ignore a late/duplicate/out-of-order update.
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
    }
}
