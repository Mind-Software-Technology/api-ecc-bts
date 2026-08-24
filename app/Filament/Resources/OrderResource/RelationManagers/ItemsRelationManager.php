<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
            ])
            ->bulkActions([]);
    }
}
