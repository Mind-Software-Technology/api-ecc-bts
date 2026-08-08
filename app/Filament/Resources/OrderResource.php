<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Notifications\OrderQuoteReady;
use App\Support\TablePolling;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Pesanan & Pembayaran';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'awaiting_quote')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pesanan menunggu penawaran harga';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_no')
                    ->label('No. Pesanan')
                    ->disabled(),
                Forms\Components\TextInput::make('user.name')
                    ->label('Pelanggan')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
                Forms\Components\TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->disabled(),
                Forms\Components\TextInput::make('total')
                    ->label('Total')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            // Pesanan baru harus segera terlihat tanpa admin me-refresh halaman.
            // Berhenti selama modal (mis. "Set Harga") terbuka — lihat TablePolling.
            ->poll(TablePolling::whileIdle())
            ->columns([
                Tables\Columns\TextColumn::make('order_no')
                    ->label('No. Pesanan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pelanggan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'quoted', 'awaiting_payment' => 'warning',
                        'awaiting_quote', 'pending' => 'gray',
                        'failed', 'cancelled', 'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::quoteAction(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum ada pesanan')
            ->emptyStateDescription('Pesanan dari pelanggan akan muncul di sini setelah mereka checkout.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function statusLabels(): array
    {
        return [
            'awaiting_quote' => 'Menunggu Penawaran',
            'quoted' => 'Sudah Ditawar',
            'pending' => 'Pending',
            'awaiting_payment' => 'Menunggu Pembayaran',
            'paid' => 'Lunas',
            'failed' => 'Gagal',
            'cancelled' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ];
    }

    public static function quoteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('quote')
            ->label('Set Harga')
            ->icon('heroicon-o-currency-dollar')
            ->color('primary')
            ->visible(fn (Order $record): bool => in_array($record->status, ['awaiting_quote', 'quoted']))
            ->form(fn (Order $record): array => $record->items->map(
                fn ($item) => Forms\Components\TextInput::make("prices.{$item->id}")
                    ->label($item->title_snapshot." (qty {$item->qty})")
                    ->numeric()
                    ->minValue(0)
                    ->default($item->price_snapshot)
                    ->required()
                    ->prefix('Rp'),
            )->all())
            ->action(function (array $data, Order $record): void {
                $prices = collect($data['prices'] ?? []);

                DB::transaction(function () use ($record, $prices) {
                    $subtotal = 0;
                    foreach ($record->items as $item) {
                        $price = (int) ($prices[$item->id] ?? 0);
                        $lineTotal = $price * $item->qty;
                        $subtotal += $lineTotal;
                        $item->update(['price_snapshot' => $price, 'line_total' => $lineTotal]);
                    }
                    $record->update(['status' => 'quoted', 'subtotal' => $subtotal, 'total' => $subtotal]);
                });

                $record->refresh()->load('items');
                // Notify User, bukan route('mail', ...): notifikasi on-demand
                // tidak punya alamat untuk channel database maupun web push.
                $record->user?->notify(new OrderQuoteReady($record));

                Notification::make()
                    ->title('Harga pesanan berhasil disimpan')
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
