<?php

namespace App\Filament\Pages;

use App\Models\SiteConfig;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSiteConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan Situs';

    protected static ?string $title = 'Pengaturan Situs';

    protected static string $view = 'filament.pages.manage-site-config';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteConfig::firstOrFail()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Identitas Merek')
                    ->schema([
                        Forms\Components\TextInput::make('brand_name')
                            ->label('Nama Merek')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('logo_url')
                            ->label('URL Logo')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('Kontak')
                    ->schema([
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email Kontak')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telepon Kontak')
                            ->maxLength(30),
                        Forms\Components\TextInput::make('address')
                            ->label('Alamat')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Rekening Bank')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('bank_account_number')
                            ->label('Nomor Rekening')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('bank_account_holder')
                            ->label('Nama Pemilik Rekening')
                            ->maxLength(255),
                    ])->columns(3),
                Forms\Components\Section::make('Tautan Sosial Media')
                    ->schema([
                        Forms\Components\Repeater::make('social_links')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('platform')
                                    ->label('Platform')
                                    ->required(),
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Tautan')
                            ->defaultItems(0),
                    ]),
                Forms\Components\Section::make('Menu Navigasi')
                    ->schema([
                        Forms\Components\Repeater::make('nav_items')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label('Label')
                                    ->required(),
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Menu')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteConfig::firstOrFail()->update($data);

        Notification::make()
            ->title('Pengaturan situs berhasil disimpan')
            ->success()
            ->send();
    }
}
