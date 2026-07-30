<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Testimonial;

class StatController extends Controller
{
    /**
     * Homepage stats band. "Klien Terlayani" and "Order Selesai" are real
     * counts from paid orders (zero until real transactions land, same
     * approach as AboutStatsController); "Rating Kepuasan" is the live
     * average of testimonial ratings; "Respon Cepat" is a fixed operational
     * commitment (not derived from any table), same treatment as
     * `komitmen_kualitas` in about-stats.
     */
    public function index()
    {
        $paidOrders = Order::where('status', 'paid');

        $orderSelesai = (clone $paidOrders)->count();

        $klienTerlayani = (clone $paidOrders)
            ->distinct()
            ->get(['user_id', 'guest_email'])
            ->map(fn ($order) => $order->user_id ?? strtolower((string) $order->guest_email))
            ->unique()
            ->count();

        $ratingKepuasan = round((float) Testimonial::avg('rating'), 1);

        return [
            'data' => [
                ['id' => 'klien_terlayani', 'value' => $klienTerlayani, 'suffix' => '+', 'label' => 'Klien Terlayani'],
                ['id' => 'order_selesai', 'value' => $orderSelesai, 'suffix' => '+', 'label' => 'Order Selesai'],
                ['id' => 'rating_kepuasan', 'value' => $ratingKepuasan, 'suffix' => '/5', 'label' => 'Rating Kepuasan'],
                ['id' => 'respon_cepat', 'value' => 24, 'suffix' => '/7', 'label' => 'Respon Cepat'],
            ],
        ];
    }
}
