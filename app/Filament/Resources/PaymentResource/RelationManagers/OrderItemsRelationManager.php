<?php

namespace App\Filament\Resources\PaymentResource\RelationManagers;

use App\Filament\Concerns\HasOrderItemFileListAction;
use App\Notifications\OrderResultReady;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class OrderItemsRelationManager extends RelationManager
{
    use HasOrderItemFileListAction;

    protected static string $relationship = 'orderItems';

    protected static ?string $title = 'Item Pesanan';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_snapshot')
            ->columns([
                Tables\Columns\TextColumn::make('title_snapshot')
                    ->label('Layanan'),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric(),
                Tables\Columns\TextColumn::make('price_snapshot')
                    ->label('Harga Satuan')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Subtotal')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('attachments_count')
                    ->label('Lampiran')
                    ->getStateUsing(fn ($record) => "{$record->attachments()->count()}/{$record->qty}"),
                Tables\Columns\TextColumn::make('results_count')
                    ->label('Hasil Dikirim')
                    ->getStateUsing(fn ($record) => "{$record->results()->count()}/{$record->qty}"),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                static::fileListAction('downloadAttachment', 'Lampiran Pelanggan', 'heroicon-o-paper-clip', 'attachments', 'admin.order-item-attachments.download'),
                static::fileListAction('downloadResult', 'Hasil Terkirim', 'heroicon-o-arrow-down-tray', 'results', 'admin.order-item-results.download'),
                Tables\Actions\Action::make('uploadResult')
                    ->label(fn ($record) => $record->results()->exists() ? 'Perbarui Hasil' : 'Unggah Hasil')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        Forms\Components\FileUpload::make('result')
                            ->label('Berkas Hasil')
                            ->storeFiles(false)
                            ->multiple()
                            ->maxFiles(fn ($record) => $record->qty)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->maxSize(51200)
                            ->required(),
                    ])
                    ->action(function (array $data, $record): void {
                        $isRevision = $record->results()->exists();

                        // Same qty-capped, replace-oldest rule as customer attachment
                        // uploads (OrderController::uploadAttachment) — an item ordered
                        // with qty > 1 can carry up to `qty` results, one per unit.
                        // ->multiple() means $data['result'] can hold several files at
                        // once (e.g. all 3 for a qty=3 item in one submission), so the
                        // cap is enforced per file as each one is added, not just once.
                        /** @var TemporaryUploadedFile $file */
                        foreach ($data['result'] as $file) {
                            if ($record->results()->count() >= $record->qty) {
                                $oldest = $record->results()->oldest('id')->first();
                                Storage::disk('local')->delete($oldest->path);
                                $oldest->delete();
                            }

                            $record->results()->create([
                                'path' => $file->store('order-results', 'local'),
                                'original_name' => $file->getClientOriginalName(),
                            ]);
                        }

                        // Legacy single-file columns mirror the most recently uploaded
                        // result, same convention as attachment_path/attachment_original_name
                        // on OrderController::uploadAttachment — anything still reading
                        // these directly (customer API, notification mail) keeps working.
                        $latest = $record->results()->latest('id')->first();
                        $record->update([
                            'result_path' => $latest->path,
                            'result_original_name' => $latest->original_name,
                            'result_delivered_at' => now(),
                        ]);

                        // Notify User, bukan route('mail', ...): notifikasi on-demand
                        // tidak punya alamat untuk channel database maupun web push.
                        $record->order->user?->notify(
                            new OrderResultReady($record->order, $record->fresh(), $isRevision)
                        );

                        Notification::make()
                            ->title('Hasil layanan berhasil diunggah')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
