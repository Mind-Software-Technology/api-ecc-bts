<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentStatusSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_no' => 'required|string|exists:orders,order_no',
            'payment_type' => 'required|in:bank_transfer,echannel,gopay,shopeepay,qris,credit_card,cstore',
            'bank' => 'required_if:payment_type,bank_transfer|in:bca,bni,bri,permata',
            'store' => 'required_if:payment_type,cstore|in:indomaret,alfamart',
            'card_token' => 'required_if:payment_type,credit_card|string',
        ]);

        $order = Order::findAccessibleOrFail($data['order_no'], $request);
        abort_unless(in_array($order->status, ['pending', 'awaiting_payment']), 422, 'Order is not payable.');

        $midtransOrderId = $order->order_no.'-'.($order->payments()->count() + 1);

        try {
            $response = \Midtrans\CoreApi::charge($this->chargeParams($order, $data, $midtransOrderId));
        } catch (\Exception $e) {
            abort(422, 'Gagal memproses pembayaran: '.$e->getMessage());
        }

        $channelDetail = match ($data['payment_type']) {
            'bank_transfer' => $data['bank'],
            'echannel' => 'mandiri',
            'cstore' => $data['store'],
            default => null,
        };

        [$vaNumber, $qrUrl, $deeplinkUrl, $paymentCode] = $this->extractPaymentFields($data['payment_type'], $response);

        $payment = DB::transaction(function () use ($order, $midtransOrderId, $data, $response, $channelDetail, $vaNumber, $qrUrl, $deeplinkUrl, $paymentCode) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'midtrans_order_id' => $midtransOrderId,
                'transaction_id' => $response->transaction_id ?? null,
                'payment_type' => $data['payment_type'],
                'channel_detail' => $channelDetail,
                'gross_amount' => (int) ($response->gross_amount ?? $order->total),
                'transaction_status' => $response->transaction_status ?? 'pending',
                'fraud_status' => $response->fraud_status ?? null,
                'va_number' => $vaNumber,
                'qr_url' => $qrUrl,
                'deeplink_url' => $deeplinkUrl,
                'payment_code' => $paymentCode,
                'expiry_time' => $response->expiry_time ?? null,
                'raw_response' => json_decode(json_encode($response), true),
            ]);

            $order->update(['status' => 'awaiting_payment']);

            return $payment;
        });

        return new PaymentResource($payment);
    }

    public function status(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        $payment = $order->payments()->latest()->firstOrFail();

        // Status normally lands via the Midtrans webhook, but that can be
        // missed (unreachable notification URL, dev/local backend, etc.) —
        // so a still-pending payment gets one active poll to Midtrans here,
        // letting the frontend's status polling self-heal instead of
        // sticking on "awaiting_payment" forever.
        if (! in_array($payment->transaction_status, PaymentStatusSync::TERMINAL_STATUSES)) {
            try {
                $response = \Midtrans\Transaction::status($payment->midtrans_order_id);
                PaymentStatusSync::apply($payment, $response->transaction_status, $response->fraud_status ?? null);
                $payment = $payment->fresh();
            } catch (\Exception) {
                // Midtrans unreachable/erroring — leave status as-is, next poll retries.
            }
        }

        return new PaymentResource($payment);
    }

    public function cancel(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        abort_if($order->status === 'paid', 422, 'Order sudah dibayar, tidak bisa dibatalkan.');

        $payment = $order->payments()->where('transaction_status', 'pending')->latest()->first();
        abort_if(! $payment, 422, 'Tidak ada transaksi pending untuk dibatalkan.');

        // Local status can drift from Midtrans's actual status if the webhook
        // never arrived — e.g. some payment methods (QRIS/GoPay) auto-expire
        // on Midtrans's side in minutes, long before our own record catches
        // up. Sync first so we don't call Transaction::cancel() on something
        // Midtrans already closed (that call 412s with a confusing raw error).
        try {
            $latest = \Midtrans\Transaction::status($payment->midtrans_order_id);
            PaymentStatusSync::apply($payment, $latest->transaction_status, $latest->fraud_status ?? null);
            $payment = $payment->fresh();
            $order = $order->fresh();
        } catch (\Exception) {
            // Midtrans unreachable — fall through, the direct cancel call below still tries.
        }

        abort_if($order->status === 'paid', 422, 'Order sudah dibayar, tidak bisa dibatalkan.');
        abort_if($payment->transaction_status !== 'pending', 422, 'Transaksi ini sudah tidak berstatus menunggu pembayaran (mis. kedaluwarsa di Midtrans). Silakan muat ulang halaman.');

        try {
            $response = \Midtrans\Transaction::cancel($payment->midtrans_order_id);
        } catch (\Exception $e) {
            abort(422, 'Gagal membatalkan transaksi: '.$e->getMessage());
        }

        DB::transaction(function () use ($payment, $order, $response) {
            $payment->update(['transaction_status' => $response->transaction_status ?? 'cancel']);
            $order->update(['status' => 'cancelled']);
        });

        return new PaymentResource($payment->fresh());
    }

    private function chargeParams(Order $order, array $data, string $midtransOrderId): array
    {
        $base = [
            'payment_type' => $data['payment_type'],
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $order->total,
            ],
            'item_details' => $order->items->map(fn ($item) => [
                'id' => $item->service_id,
                'price' => $item->price_snapshot,
                'quantity' => $item->qty,
                'name' => $item->title_snapshot,
            ])->all(),
            'customer_details' => [
                'first_name' => $order->guest_name ?? $order->user?->name,
                'email' => $order->guest_email ?? $order->user?->email,
                'phone' => $order->guest_phone ?? $order->user?->phone,
            ],
        ];

        $extra = match ($data['payment_type']) {
            'bank_transfer' => ['bank_transfer' => ['bank' => $data['bank']]],
            'echannel' => ['echannel' => [
                'bill_info1' => 'Pembayaran',
                'bill_info2' => $order->order_no,
            ]],
            'gopay' => ['gopay' => []],
            'shopeepay' => ['shopeepay' => []],
            'credit_card' => ['credit_card' => ['token_id' => $data['card_token'], 'authentication' => true]],
            'cstore' => ['cstore' => ['store' => $data['store'], 'message' => "Pembayaran {$order->order_no}"]],
            default => [],
        };

        return array_merge($base, $extra);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} [va_number, qr_url, deeplink_url, payment_code]
     */
    private function extractPaymentFields(string $paymentType, object $response): array
    {
        return match (true) {
            $paymentType === 'bank_transfer' && isset($response->va_numbers[0]) => [$response->va_numbers[0]->va_number, null, null, null],
            $paymentType === 'bank_transfer' && isset($response->permata_va_number) => [$response->permata_va_number, null, null, null],
            $paymentType === 'echannel' => [$response->bill_key ?? null, null, null, $response->biller_code ?? null],
            in_array($paymentType, ['gopay', 'shopeepay', 'qris']) => [null, $this->actionUrl($response, 'generate-qr-code'), $this->actionUrl($response, 'deeplink-redirect'), null],
            $paymentType === 'credit_card' => [null, null, $response->redirect_url ?? null, null],
            $paymentType === 'cstore' => [null, null, null, $response->payment_code ?? null],
            default => [null, null, null, null],
        };
    }

    private function actionUrl(object $response, string $name): ?string
    {
        foreach ($response->actions ?? [] as $action) {
            if (($action->name ?? null) === $name) {
                return $action->url ?? null;
            }
        }

        return null;
    }
}
