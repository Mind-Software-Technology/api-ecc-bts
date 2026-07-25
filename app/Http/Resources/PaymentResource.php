<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Raw Midtrans response is deliberately excluded — doc Bagian 6.7:
     * never show raw_response/raw_payload unfiltered in any admin UI.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'midtrans_order_id' => $this->midtrans_order_id,
            'transaction_id' => $this->transaction_id,
            'payment_type' => $this->payment_type,
            'channel_detail' => $this->channel_detail,
            'gross_amount' => $this->gross_amount,
            'transaction_status' => $this->transaction_status,
            'fraud_status' => $this->fraud_status,
            'va_number' => $this->va_number,
            'qr_url' => $this->qr_url,
            'deeplink_url' => $this->deeplink_url,
            'payment_code' => $this->payment_code,
            'expiry_time' => $this->expiry_time,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
