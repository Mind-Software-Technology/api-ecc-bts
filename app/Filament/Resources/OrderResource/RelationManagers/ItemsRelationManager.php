<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Notifications\OrderResultReady;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

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
                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Lampiran')
                    ->boolean()
                    ->getStateUsing(fn ($record) => (bool) $record->attachment_path),
                Tables\Columns\TextColumn::make('result_delivered_at')
                    ->label('Hasil Dikirim')
                    ->dateTime()
                    ->placeholder('Belum ada'),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('downloadAttachment')
                    ->label('Unduh Lampiran')
                    ->icon('heroicon-o-paper-clip')
                    ->url(fn ($record) => $record->attachment_path
                        ? route('admin.order-items.attachment', $record)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->attachment_path),
                Tables\Actions\Action::make('downloadResult')
                    ->label('Unduh Hasil')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->result_path
                        ? route('admin.order-items.result', $record)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => (bool) $record->result_path),
                Tables\Actions\Action::make('uploadResult')
                    ->label(fn ($record) => $record->result_path ? 'Perbarui Hasil' : 'Unggah Hasil')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->form([
                        Forms\Components\FileUpload::make('result')
                            ->label('Berkas Hasil')
                            ->storeFiles(false)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'image/jpeg',
                                'image/png',
                            ])
                            ->maxSize(10240)
                            ->required(),
                    ])
                    ->action(function (array $data, $record): void {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['result'];

                        $isRevision = $record->result_path !== null;

                        if ($isRevision) {
                            Storage::disk('local')->delete($record->result_path);
                        }

                        $record->update([
                            'result_path' => $file->store('order-results', 'local'),
                            'result_original_name' => $file->getClientOriginalName(),
                            'result_delivered_at' => now(),
                        ]);

                        NotificationFacade::route('mail', $record->order->guest_email)
                            ->notify(new OrderResultReady($record->order, $record->fresh(), $isRevision));

                        Notification::make()
                            ->title('Hasil layanan berhasil diunggah')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
