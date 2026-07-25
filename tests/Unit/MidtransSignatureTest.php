<?php

namespace Tests\Unit;

use App\Support\MidtransSignature;
use PHPUnit\Framework\TestCase;

class MidtransSignatureTest extends TestCase
{
    public function test_valid_signature_passes(): void
    {
        $orderId = 'INV-20260717-0001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $serverKey = 'test-server-key';
        $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $this->assertTrue(MidtransSignature::isValid($orderId, $statusCode, $grossAmount, $signatureKey, $serverKey));
    }

    public function test_tampered_fields_fail_verification(): void
    {
        $orderId = 'INV-20260717-0001';
        $statusCode = '200';
        $grossAmount = '150000.00';
        $serverKey = 'test-server-key';
        $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $this->assertFalse(MidtransSignature::isValid($orderId.'x', $statusCode, $grossAmount, $signatureKey, $serverKey));
        $this->assertFalse(MidtransSignature::isValid($orderId, '201', $grossAmount, $signatureKey, $serverKey));
        $this->assertFalse(MidtransSignature::isValid($orderId, $statusCode, '150000.01', $signatureKey, $serverKey));
        $this->assertFalse(MidtransSignature::isValid($orderId, $statusCode, $grossAmount, $signatureKey, 'wrong-server-key'));
    }
}
