<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

class PaymentFileController extends Controller
{
    public function proof(Payment $payment)
    {
        abort_if(! $payment->proof_path, 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->proof_original_name);
    }
}
