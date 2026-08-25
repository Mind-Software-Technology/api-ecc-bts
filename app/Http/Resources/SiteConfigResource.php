<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiteConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'brand_name' => $this->brand_name,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : $this->logo_url,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'bank_accounts' => $this->bank_accounts ?? [],
            'payment_method_mode' => $this->payment_method_mode,
            'social_links' => $this->social_links,
            'nav_items' => $this->nav_items,
            'hero' => $this->hero ?? [],

            // Angka hero dihitung langsung, bukan diketik admin — jadi naik
            // sendiri tiap ada pesanan lunas baru. Dititipkan di payload ini
            // supaya beranda tidak perlu request tambahan: Navbar sudah
            // memanggil endpoint ini di setiap halaman.
            //
            // count('user_id') mengabaikan baris ber-user_id NULL dengan
            // sendirinya, jadi pesanan tamu lama tidak ikut terhitung.
            // ponytail: dua COUNT langsung, sudah ditopang index orders.status;
            // bungkus Cache::remember kalau tabelnya nanti jutaan baris.
            'stats' => [
                'works_done' => Order::where('status', 'paid')->count(),
                'happy_clients' => Order::where('status', 'paid')->distinct()->count('user_id'),
            ],
        ];
    }
}
