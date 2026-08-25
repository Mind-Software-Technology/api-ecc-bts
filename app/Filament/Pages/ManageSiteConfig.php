<?php

namespace App\Filament\Pages;

use App\Models\SiteConfig;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('site')
                            ->visibility('public'),
                        Forms\Components\TextInput::make('logo_url')
                            ->label('URL Logo (opsional)')
                            ->helperText('Diisi otomatis kalau upload logo. Isi manual hanya jika logo di-host di luar.')
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
                Forms\Components\Section::make('Metode Pembayaran')
                    ->schema([
                        Forms\Components\Select::make('payment_method_mode')
                            ->label('Metode Pembayaran Aktif')
                            ->options([
                                'midtrans' => 'Midtrans (Otomatis)',
                                'manual' => 'Transfer Manual',
                                'both' => 'Midtrans & Transfer Manual',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Pilih metode yang tersedia untuk pelanggan saat checkout.'),
                    ]),
                Forms\Components\Section::make('Rekening Bank')
                    ->description('Minimal satu rekening wajib diisi kalau Transfer Manual aktif — pelanggan memilih salah satunya saat checkout.')
                    ->schema([
                        Forms\Components\Repeater::make('bank_accounts')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('bank_name')
                                    ->label('Nama Bank')
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('account_number')
                                    ->label('Nomor Rekening')
                                    ->required()
                                    ->maxLength(60),
                                Forms\Components\TextInput::make('account_holder')
                                    ->label('Nama Pemilik Rekening')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(3)
                            ->addActionLabel('Tambah Rekening')
                            ->itemLabel(fn (array $state): ?string => $state['bank_name'] ?? null)
                            ->defaultItems(0)
                            ->minItems(fn (Get $get): int => in_array($get('payment_method_mode'), ['manual', 'both']) ? 1 : 0)
                            ->reorderable()
                            ->collapsible(),
                    ]),
                Forms\Components\Section::make('Tautan Sosial Media')
                    ->schema([
                        Forms\Components\Repeater::make('social_links')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('platform')
                                    ->label('Platform'),
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->url(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Tautan')
                            ->defaultItems(0),
                    ]),
                Forms\Components\Section::make('Hero Halaman Depan')
                    ->description('Teks besar di bagian paling atas beranda. Dua angka pertama dihitung otomatis dari data pesanan, jadi hanya labelnya yang bisa diubah di sini.')
                    ->schema([
                        Forms\Components\TextInput::make('hero.eyebrow')
                            ->label('Teks Kecil di Atas Judul')
                            ->maxLength(80),
                        Forms\Components\TextInput::make('hero.title')
                            ->label('Judul (bagian putih)')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('hero.title_highlight')
                            ->label('Judul (bagian oranye)')
                            ->helperText('Lanjutan judul yang tampil berwarna.')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('hero.subtitle')
                            ->label('Paragraf Penjelas')
                            ->rows(3)
                            ->maxLength(400)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('hero.stat_works_label')
                            ->label('Label Angka Ke-1')
                            ->helperText('Angkanya = jumlah pesanan lunas, otomatis.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('hero.stat_clients_label')
                            ->label('Label Angka Ke-2')
                            ->helperText('Angkanya = jumlah pelanggan yang punya pesanan lunas, otomatis.')
                            ->maxLength(40),
                        Forms\Components\TextInput::make('hero.stat_quality_value')
                            ->label('Angka Ke-3')
                            ->helperText('Yang ini diketik manual, mis. 100%.')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('hero.stat_quality_label')
                            ->label('Label Angka Ke-3')
                            ->maxLength(40),
                    ])->columns(2),
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
                                    ->helperText('Path relatif seperti /kategori, atau URL penuh.')
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
