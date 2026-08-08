<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Konten Layanan';

    protected static ?string $navigationLabel = 'Layanan';

    protected static ?string $modelLabel = 'Layanan';

    protected static ?string $pluralModelLabel = 'Layanan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(80),
                Forms\Components\Select::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'title')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(160),
                Forms\Components\TextInput::make('tagline')
                    ->label('Tagline')
                    ->required()
                    ->maxLength(240),
                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TagsInput::make('points')
                    ->label('Poin-poin')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('icon')
                    ->label('Ikon')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('accent')
                    ->label('Warna Aksen')
                    ->required()
                    ->maxLength(20),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Gambar Layanan')
                    ->image()
                    ->disk('public')
                    ->directory('services')
                    ->visibility('public'),
                Forms\Components\TextInput::make('image_url')
                    ->label('URL Gambar (opsional)')
                    ->url()
                    ->maxLength(255),
                Forms\Components\TextInput::make('image_alt')
                    ->label('Teks Alternatif Gambar')
                    ->maxLength(160),
                Forms\Components\TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\TextInput::make('rating')
                    ->label('Rating')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Forms\Components\TextInput::make('reviews_count')
                    ->label('Jumlah Ulasan')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('badge')
                    ->label('Badge')
                    ->maxLength(20)
                    ->default(null),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->required(),
                Forms\Components\Toggle::make('requires_attachment')
                    ->label('Butuh Lampiran')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Gambar')
                    ->disk('public'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.title')
                    ->label('Kategori')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('badge')
                    ->label('Badge')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('requires_attachment')
                    ->label('Butuh Lampiran')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'title'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada layanan')
            ->emptyStateDescription('Tambahkan layanan pertama Anda supaya bisa tampil di website.')
            ->emptyStateIcon('heroicon-o-briefcase');
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
