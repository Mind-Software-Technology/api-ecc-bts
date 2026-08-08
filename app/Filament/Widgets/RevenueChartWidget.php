<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Pendapatan 12 Bulan Terakhir';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $since = now()->subMonths(11)->startOfMonth();

        $byMonth = Order::where('status', 'paid')
            ->where('created_at', '>=', $since)
            ->get(['total', 'created_at'])
            ->groupBy(fn ($order) => $order->created_at->format('Y-m'));

        $months = collect(range(11, 0))->map(fn ($monthsAgo) => now()->subMonths($monthsAgo)->format('Y-m'));

        $revenue = $months->map(fn ($key) => (int) $byMonth->get($key, collect())->sum('total'));
        $orders = $months->map(fn ($key) => $byMonth->get($key, collect())->count());

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan (Rp)',
                    'data' => $revenue->values(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Jumlah Pesanan',
                    'data' => $orders->values(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn ($key) => Carbon::createFromFormat('Y-m', $key)->translatedFormat('M Y'))->values(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
