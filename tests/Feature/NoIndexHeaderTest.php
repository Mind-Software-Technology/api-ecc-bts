<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Domain ini tidak boleh muncul di hasil pencarian — lihat
 * app/Http/Middleware/NoIndex.php.
 */
class NoIndexHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_endpoints_are_marked_noindex(): void
    {
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_filament_admin_login_is_marked_noindex(): void
    {
        // Justru halaman inilah alasan middleware-nya dipasang global, bukan
        // hanya di grup 'api': panel Filament jalan lewat grup 'web'.
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_file_downloads_are_marked_noindex(): void
    {
        // Unduhan lampiran memakai Storage::download() yang mengembalikan
        // StreamedResponse — kelas Symfony polos tanpa helper ->header()
        // milik Laravel. Middleware-nya memakai headers->set(); tanpa itu
        // rute ini fatal error, bukan sekadar kehilangan header.
        Storage::fake('local');

        $user = User::factory()->create();
        $category = Category::create([
            'slug' => 'kategori-'.uniqid(),
            'title' => 'Kategori Test',
            'short_desc' => 'Short desc',
            'description' => 'Description',
            'icon' => 'icon',
            'accent' => 'blue',
        ]);
        $service = Service::create([
            'slug' => 'layanan-'.uniqid(),
            'category_id' => $category->id,
            'title' => 'Layanan Test',
            'tagline' => 'Tagline',
            'description' => 'Description',
            'points' => [],
            'icon' => 'icon',
            'accent' => 'blue',
            'price' => 100000,
            'is_active' => true,
            'requires_attachment' => false,
        ]);

        $order = Order::create([
            'order_no' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'guest_name' => $user->name,
            'guest_email' => $user->email,
            'guest_phone' => '081234567890',
            'status' => 'awaiting_quote',
        ]);

        Storage::disk('local')->put('attachments/naskah.pdf', 'isi berkas');
        $item = $order->items()->create([
            'service_id' => $service->id,
            'title_snapshot' => $service->title,
            'qty' => 1,
            'attachment_path' => 'attachments/naskah.pdf',
            'attachment_original_name' => 'naskah.pdf',
        ]);

        $this->actingAs($user)
            ->get("/api/orders/{$order->order_no}/items/{$item->id}/attachment")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
