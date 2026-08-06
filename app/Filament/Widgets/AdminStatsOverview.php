<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $revenueThisMonth = Order::where('status', 'paid')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $awaitingQuote = Order::where('status', 'awaiting_quote')->count();
        $paidThisMonth = Order::where('status', 'paid')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $newMessages = ContactMessage::where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Pendapatan Bulan Ini', 'Rp '.number_format((float) $revenueThisMonth, 0, ',', '.'))
                ->description($paidThisMonth.' pesanan lunas bulan ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Menunggu Penawaran Harga', $awaitingQuote)
                ->description($awaitingQuote > 0 ? 'Perlu ditindaklanjuti' : 'Semua sudah ditawar')
                ->descriptionIcon('heroicon-m-clock')
                ->color($awaitingQuote > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.orders.index', ['tableFilters[status][value]' => 'awaiting_quote'])),
            Stat::make('Pesan Kontak Baru', $newMessages)
                ->description('7 hari terakhir')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('info')
                ->url(route('filament.admin.resources.contact-messages.index')),
        ];
    }
}
