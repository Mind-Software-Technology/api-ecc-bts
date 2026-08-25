<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SiteConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teks hero bisa diubah admin, sedangkan dua angkanya dihitung dari data
 * pesanan — lihat SiteConfigResource dan Filament ManageSiteConfig.
 */
class SiteConfigHeroTest extends TestCase
{
    use RefreshDatabase;

    private function makeConfig(array $hero = []): SiteConfig
    {
        return SiteConfig::create([
            'brand_name' => 'ECC-BTS',
            'hero' => $hero,
        ]);
    }

    private function makeOrder(?User $user, string $status): Order
    {
        return Order::create([
            'order_no' => 'INV-'.uniqid(),
            'user_id' => $user?->id,
            'status' => $status,
        ]);
    }

    public function test_hero_text_comes_from_the_database(): void
    {
        $this->makeConfig([
            'title' => 'Judul Baru dari Admin',
            'title_highlight' => 'Sorotan Baru',
            'stat_quality_value' => '99%',
        ]);

        $this->getJson('/api/site-config')
            ->assertOk()
            ->assertJsonPath('hero.title', 'Judul Baru dari Admin')
            ->assertJsonPath('hero.title_highlight', 'Sorotan Baru')
            ->assertJsonPath('hero.stat_quality_value', '99%');
    }

    public function test_hero_is_an_empty_object_when_never_filled(): void
    {
        // Kolomnya nullable; frontend harus dapat [] supaya bisa jatuh ke teks
        // bawaannya, bukan null yang bikin pembacaan properti meledak.
        SiteConfig::create(['brand_name' => 'ECC-BTS']);

        $this->getJson('/api/site-config')
            ->assertOk()
            ->assertJsonPath('hero', []);
    }

    public function test_stats_only_count_paid_orders(): void
    {
        $this->makeConfig();
        $user = User::factory()->create();

        $this->makeOrder($user, 'paid');
        $this->makeOrder($user, 'paid');
        // Yang belum lunas tidak boleh ikut terhitung sebagai karya selesai.
        $this->makeOrder($user, 'pending');
        $this->makeOrder($user, 'awaiting_quote');
        $this->makeOrder($user, 'cancelled');

        $this->getJson('/api/site-config')
            ->assertOk()
            ->assertJsonPath('stats.works_done', 2);
    }

    public function test_happy_clients_counts_people_not_orders(): void
    {
        $this->makeConfig();
        $andi = User::factory()->create();
        $budi = User::factory()->create();

        // Andi memesan tiga kali — tetap satu orang.
        $this->makeOrder($andi, 'paid');
        $this->makeOrder($andi, 'paid');
        $this->makeOrder($andi, 'paid');
        $this->makeOrder($budi, 'paid');
        // Belum lunas, jadi Citra belum dihitung sebagai klien.
        $this->makeOrder(User::factory()->create(), 'pending');

        $this->getJson('/api/site-config')
            ->assertOk()
            ->assertJsonPath('stats.works_done', 4)
            ->assertJsonPath('stats.happy_clients', 2);
    }

    public function test_guest_orders_without_a_user_do_not_break_the_client_count(): void
    {
        // orders.user_id nullable (dan nullOnDelete), jadi baris ber-user NULL
        // memang mungkin ada. COUNT(user_id) harus melewatinya, bukan
        // menghitungnya sebagai satu klien misterius.
        $this->makeConfig();
        $user = User::factory()->create();

        $this->makeOrder($user, 'paid');
        $this->makeOrder(null, 'paid');

        $this->getJson('/api/site-config')
            ->assertOk()
            ->assertJsonPath('stats.works_done', 2)
            ->assertJsonPath('stats.happy_clients', 1);
    }
}
