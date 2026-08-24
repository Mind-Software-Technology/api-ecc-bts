<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Support\PaymentStatusSync;
use App\Support\TablePolling;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Pesanan & Pembayaran';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('method', 'manual')
            ->where('transaction_status', 'pending')
            ->whereNotNull('proof_path')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bukti transfer manual menunggu verifikasi';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order.order_no')
                    ->label('No. Pesanan')
                    ->disabled(),
                Forms\Components\TextInput::make('midtrans_order_id')
                    ->label('Midtrans Order ID')
                    ->disabled(),
                Forms\Components\TextInput::make('transaction_id')
                    ->label('Transaction ID')
                    ->disabled(),
                Forms\Components\TextInput::make('payment_type')
                    ->label('Tipe Pembayaran')
                    ->disabled(),
                Forms\Components\TextInput::make('channel_detail')
                    ->label('Detail Channel')
                    ->disabled(),
                Forms\Components\TextInput::make('gross_amount')
                    ->label('Jumlah')
                    ->disabled(),
                Forms\Components\TextInput::make('transaction_status')
                    ->label('Status Transaksi')
                    ->disabled(),
                Forms\Components\TextInput::make('fraud_status')
                    ->label('Status Fraud')
                    ->disabled(),
                Forms\Components\TextInput::make('va_number')
                    ->label('Nomor VA')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('expiry_time')
                    ->label('Kedaluwarsa')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Dibayar Pada')
                    ->disabled(),
                Forms\Components\Textarea::make('raw_response')
                    ->label('Respons Mentah')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            // Status Midtrans berubah lewat webhook, bukan lewat aksi admin — polling
            // supaya perubahannya muncul sendiri. Berhenti selama modal
            // terbuka — lihat TablePolling.
            ->poll(TablePolling::whileIdle())
            ->columns([
                Tables\Columns\TextColumn::make('order.order_no')
                    ->label('No. Pesanan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Tipe'),
                Tables\Columns\TextColumn::make('method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'Transfer Manual' : 'Midtrans')
                    ->color(fn (string $state): string => $state === 'manual' ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('proof_path')
                    ->label('Bukti')
                    ->boolean()
                    ->getStateUsing(fn (Payment $record): bool => (bool) $record->proof_path),
                Tables\Columns\TextColumn::make('bank_account_snapshot')
                    ->label('Rekening Tujuan')
                    ->formatStateUsing(fn (?array $state): string => $state ? "{$state['bank_name']} — {$state['account_number']}" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'settlement', 'capture' => 'success',
                        'pending' => 'warning',
                        'deny', 'cancel', 'expire' => 'danger',
                        'refund' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transaction_status')
                    ->label('Status Transaksi')
                    ->options([
                        'pending' => 'Pending',
                        'settlement' => 'Settlement',
                        'capture' => 'Capture',
                        'deny' => 'Deny',
                        'cancel' => 'Cancel',
                        'expire' => 'Expire',
                        'refund' => 'Refund',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('viewProof')
                    ->label('Lihat Bukti')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn (Payment $record): ?string => $record->proof_path ? route('admin.payments.proof', $record) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Payment $record): bool => (bool) $record->proof_path),
                Tables\Actions\Action::make('verifyAccept')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Pesanan akan ditandai lunas setelah pembayaran manual ini diterima.')
                    ->visible(fn (Payment $record): bool => $record->method === 'manual' && $record->transaction_status === 'pending')
                    ->action(function (Payment $record): void {
                        PaymentStatusSync::apply($record, 'settlement', null);
                        $record->fresh()->update([
                            'verified_by' => auth('admin')->id(),
                            'verified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Pembayaran diverifikasi, pesanan ditandai lunas')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('verifyReject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Pesanan akan ditandai gagal — pelanggan bisa mencoba membayar ulang.')
                    ->visible(fn (Payment $record): bool => $record->method === 'manual' && $record->transaction_status === 'pending')
                    ->action(function (Payment $record): void {
                        PaymentStatusSync::apply($record, 'deny', null);
                        $record->fresh()->update([
                            'verified_by' => auth('admin')->id(),
                            'verified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Bukti transfer ditolak')
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Transaksi pembayaran dari Midtrans akan muncul di sini.')
            ->emptyStateIcon('heroicon-o-credit-card');
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
