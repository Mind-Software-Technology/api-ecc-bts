<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteConfig;
use App\Models\User;
use App\Support\PaymentStatusSync;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Midtrans\CoreApi;
use Midtrans\Transaction;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_no' => 'required|string|exists:orders,order_no',
            'payment_type' => 'required|in:bank_transfer,echannel,gopay,shopeepay,qris,credit_card,cstore,manual_transfer',
            'bank' => 'required_if:payment_type,bank_transfer|in:bca,bni,bri,permata',
            'store' => 'required_if:payment_type,cstore|in:indomaret,alfamart',
            'card_token' => 'required_if:payment_type,credit_card|string',
            'bank_account_index' => 'required_if:payment_type,manual_transfer|integer|min:0',
        ]);

        $order = Order::findAccessibleOrFail($data['order_no'], $request);
        abort_unless(in_array($order->status, ['pending', 'awaiting_payment']), 422, 'Order is not payable.');

        $isManual = $data['payment_type'] === 'manual_transfer';
        $config = SiteConfig::firstOrFail();
        abort_if($isManual && ! $config->manualEnabled(), 422, 'Transfer manual sedang tidak tersedia.');
        abort_if(! $isManual && ! $config->midtransEnabled(), 422, 'Pembayaran online sedang tidak tersedia, silakan gunakan transfer manual.');

        $midtransOrderId = $order->order_no.'-'.($order->payments()->count() + 1);

        if ($isManual) {
            $bankAccount = ($config->bank_accounts ?? [])[$data['bank_account_index']] ?? null;
            abort_unless($bankAccount, 422, 'Rekening tujuan tidak valid.');

            $payment = DB::transaction(function () use ($order, $midtransOrderId, $bankAccount) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'midtrans_order_id' => $midtransOrderId,
                    'payment_type' => 'manual_transfer',
                    'method' => 'manual',
                    'gross_amount' => $order->total,
                    'transaction_status' => 'pending',
                    'bank_account_snapshot' => $bankAccount,
                ]);

                $order->update(['status' => 'awaiting_payment']);

                return $payment;
            });

            return new PaymentResource($payment);
        }

        try {
            $response = CoreApi::charge($this->chargeParams($order, $data, $midtransOrderId));
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
                'method' => 'midtrans',
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

    /**
     * Customer uploads/replaces bukti transfer for their pending manual
     * payment. Mirrors OrderController::uploadAttachment's storage pattern.
     */
    public function uploadProof(Request $request, string $order_no)
    {
        $data = $request->validate([
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $order = Order::findAccessibleOrFail($order_no, $request);
        $payment = $order->payments()->where('method', 'manual')->latest()->first();
        abort_unless($payment, 404);
        abort_if($payment->verified_at, 422, 'Pembayaran ini sudah diverifikasi.');

        if ($payment->proof_path) {
            Storage::disk('local')->delete($payment->proof_path);
        }

        $file = $data['proof'];
        $payment->update([
            'proof_path' => $file->store('payment-proofs', 'local'),
            'proof_original_name' => $file->getClientOriginalName(),
        ]);

        // Filament's own notification type (not a plain Illuminate\Notification
        // like the customer-facing ones) so it renders correctly in the admin
        // panel's bell — that bell already polls on its own, no page refresh needed.
        FilamentNotification::make()
            ->title('Bukti transfer baru')
            ->body("Pesanan {$order->order_no} mengunggah bukti transfer, menunggu verifikasi.")
            ->icon('heroicon-o-banknotes')
            ->actions([
                FilamentAction::make('view')
                    ->label('Lihat Pembayaran')
                    ->url(route('filament.admin.resources.payments.view', $payment))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::where('role', 'admin')->get());

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
                $response = Transaction::status($payment->midtrans_order_id);
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
        $payment = $this->cancelPendingPayment($order, 'dibatalkan');

        DB::transaction(function () use ($payment, $order) {
            $payment->save();
            $order->update(['status' => 'cancelled']);
        });

        return new PaymentResource($payment->fresh());
    }

    /**
     * Customer picked the wrong payment method — cancel the pending Midtrans
     * transaction but, unlike cancel(), send the order back to 'pending'
     * (not 'cancelled') so they can charge again with a different method
     * instead of losing the whole order.
     */
    public function changeMethod(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        $payment = $this->cancelPendingPayment($order, 'diganti');

        DB::transaction(function () use ($payment, $order) {
            $payment->save();
            $order->update(['status' => 'pending']);
        });

        return new PaymentResource($payment->fresh());
    }

    /**
     * Syncs the pending payment against Midtrans, cancels it there, and
     * returns the (unsaved) updated Payment — the caller persists it inside
     * its own transaction alongside the order status change.
     */
    private function cancelPendingPayment(Order $order, string $actionLabel): Payment
    {
        abort_if($order->status === 'paid', 422, 'Order sudah dibayar, tidak bisa '.$actionLabel.'.');

        $payment = $order->payments()->where('transaction_status', 'pending')->latest()->first();
        abort_if(! $payment, 422, 'Tidak ada transaksi pending untuk '.$actionLabel.'.');

        // Manual transfers never touch Midtrans, so there's nothing to sync
        // or cancel over there — just flip the local status directly.
        if ($payment->method === 'manual') {
            $payment->transaction_status = 'cancel';

            return $payment;
        }

        // Local status can drift from Midtrans's actual status if the webhook
        // never arrived — e.g. some payment methods (QRIS/GoPay) auto-expire
        // on Midtrans's side in minutes, long before our own record catches
        // up. Sync first so we don't call Transaction::cancel() on something
        // Midtrans already closed (that call 412s with a confusing raw error).
        try {
            $latest = Transaction::status($payment->midtrans_order_id);
            PaymentStatusSync::apply($payment, $latest->transaction_status, $latest->fraud_status ?? null);
            $payment = $payment->fresh();
            $order->refresh();
        } catch (\Exception) {
            // Midtrans unreachable — fall through, the direct cancel call below still tries.
        }

        abort_if($order->status === 'paid', 422, 'Order sudah dibayar, tidak bisa '.$actionLabel.'.');
        abort_if($payment->transaction_status !== 'pending', 422, 'Transaksi ini sudah tidak berstatus menunggu pembayaran (mis. kedaluwarsa di Midtrans). Silakan muat ulang halaman.');

        try {
            $response = Transaction::cancel($payment->midtrans_order_id);
        } catch (\Exception $e) {
            abort(422, 'Gagal '.$actionLabel.' transaksi: '.$e->getMessage());
        }

        $payment->transaction_status = $response->transaction_status ?? 'cancel';

        return $payment;
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
