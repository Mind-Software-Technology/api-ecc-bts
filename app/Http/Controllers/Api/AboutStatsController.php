<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;

class AboutStatsController extends Controller
{
    /**
     * Real counts derived from paid orders — no manually-entered numbers,
     * so the figures start at zero and grow as actual transactions land.
     */
    public function index()
    {
        $paidOrders = Order::where('status', 'paid');

        $karyaIlmiahSelesai = (clone $paidOrders)->count();

        $klienPuas = (clone $paidOrders)
            ->distinct()
            ->get(['user_id', 'guest_email'])
            ->map(fn ($order) => $order->user_id ?? strtolower((string) $order->guest_email))
            ->unique()
            ->count();

        $publikasiJurnal = OrderItem::whereHas('order', fn ($query) => $query->where('status', 'paid'))
            ->whereHas('service.category', fn ($query) => $query->where('slug', 'publikasi-penerbitan'))
            ->count();

        return [
            'data' => [
                'karya_ilmiah_selesai' => $karyaIlmiahSelesai,
                'klien_puas' => $klienPuas,
                'publikasi_jurnal' => $publikasiJurnal,
                'komitmen_kualitas' => 100,
            ],
        ];
    }
}
