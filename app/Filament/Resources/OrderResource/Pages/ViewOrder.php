<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Notifications\OrderQuoteReady;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quote')
                ->label('Set Harga')
                ->icon('heroicon-o-currency-dollar')
                ->color('primary')
                ->visible(fn (): bool => in_array($this->record->status, ['awaiting_quote', 'quoted']))
                ->form(fn (): array => $this->record->items->map(
                    fn ($item) => Forms\Components\TextInput::make("prices.{$item->id}")
                        ->label($item->title_snapshot." (qty {$item->qty})")
                        ->numeric()
                        ->minValue(0)
                        ->default($item->price_snapshot)
                        ->required()
                        ->prefix('Rp'),
                )->all())
                ->action(function (array $data): void {
                    /** @var Order $order */
                    $order = $this->record;
                    $prices = collect($data['prices'] ?? []);

                    DB::transaction(function () use ($order, $prices) {
                        $subtotal = 0;
                        foreach ($order->items as $item) {
                            $price = (int) ($prices[$item->id] ?? 0);
                            $lineTotal = $price * $item->qty;
                            $subtotal += $lineTotal;
                            $item->update(['price_snapshot' => $price, 'line_total' => $lineTotal]);
                        }
                        $order->update(['status' => 'quoted', 'subtotal' => $subtotal, 'total' => $subtotal]);
                    });

                    $order->refresh()->load('items');
                    NotificationFacade::route('mail', $order->guest_email)->notify(new OrderQuoteReady($order));

                    Notification::make()
                        ->title('Harga pesanan berhasil disimpan')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('view', ['record' => $order]));
                }),
        ];
    }
}
