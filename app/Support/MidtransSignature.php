<?php

namespace App\Support;

final class MidtransSignature
{
    /**
     * Midtrans webhook signature = sha512(order_id + status_code + gross_amount + ServerKey).
     * gross_amount must be used exactly as the string Midtrans sent (e.g. "150000.00") —
     * re-casting it to int before hashing silently breaks verification.
     */
    public static function isValid(string $orderId, string $statusCode, string $grossAmount, string $signatureKey, string $serverKey): bool
    {
        return hash_equals(hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey), $signatureKey);
    }
}
