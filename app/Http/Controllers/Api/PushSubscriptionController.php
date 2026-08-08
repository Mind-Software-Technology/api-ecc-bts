<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Langganan web push milik satu browser/perangkat.
 *
 * Payload-nya adalah bentuk mentah `PushSubscription.toJSON()` dari browser,
 * jadi frontend bisa mengirimnya apa adanya tanpa dibentuk ulang.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string',
        ]);

        // updatePushSubscription() idempotent: endpoint yang sama cuma
        // diperbarui, dan langganan yang berpindah pemilik (satu browser dipakai
        // dua akun) dilepas dari akun lama lebih dulu.
        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? null,
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string']);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->noContent();
    }
}
