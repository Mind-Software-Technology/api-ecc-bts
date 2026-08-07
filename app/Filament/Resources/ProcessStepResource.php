<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessStepResource\Pages;
use App\Filament\Resources\ProcessStepResource\RelationManagers;
use App\Models\ProcessStep;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProcessStepResource extends Resource
{
    protected static ?string $model = ProcessStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Konten Halaman';

    protected static ?string $navigationLabel = 'Langkah Proses';

    protected static ?string $modelLabel = 'Langkah Proses';

    protected static ?string $pluralModelLabel = 'Langkah Proses';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('step_number')
                    ->label('Nomor Langkah')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('icon')
                    ->label('Ikon')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(160),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('step_number')
                    ->label('Nomor')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icon')
                    ->label('Ikon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListProcessSteps::route('/'),
            'create' => Pages\CreateProcessStep::route('/create'),
            'edit' => Pages\EditProcessStep::route('/{record}/edit'),
        ];
    }
}
