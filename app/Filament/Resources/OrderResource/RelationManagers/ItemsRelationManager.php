<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Concerns\HasOrderItemFileListAction;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    use HasOrderItemFileListAction;

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
            ])
            ->bulkActions([]);
    }
}
