<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, int $total, \DateTimeInterface $createdAt): Order
    {
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
            'price' => $total,
            'is_active' => true,
            'requires_attachment' => false,
        ]);

        $order = Order::create([
            'order_no' => 'ORD-'.uniqid(),
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '081234567890',
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
        ]);
        $order->items()->create([
            'service_id' => $service->id,
            'title_snapshot' => $service->title,
            'qty' => 1,
            'price_snapshot' => $total,
            'line_total' => $total,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $order;
    }

    public function test_non_admin_cannot_access_revenue_analytics(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/admin/analytics/revenue')->assertForbidden();
    }

    public function test_revenue_analytics_returns_last_12_months_grouped_by_paid_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->makeOrder('paid', 100000, now());
        $this->makeOrder('paid', 50000, now());
        $this->makeOrder('pending', 999999, now());
        $this->makeOrder('paid', 75000, now()->subMonths(2));

        $response = $this->actingAs($admin)->getJson('/api/admin/analytics/revenue');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(12, $data);
        $this->assertSame(now()->format('Y-m'), $data[11]['month']);
        $this->assertSame(150000, $data[11]['revenue']);
        $this->assertSame(2, $data[11]['orders']);
        $this->assertSame(75000, $data[9]['revenue']);
    }
}
