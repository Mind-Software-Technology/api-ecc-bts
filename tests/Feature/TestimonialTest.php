<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, string $status, ?\DateTimeInterface $resultDeliveredAt): Order
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
            'status' => $status,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        $order->items()->create([
            'service_id' => $service->id,
            'title_snapshot' => $service->title,
            'qty' => 1,
            'price_snapshot' => 100000,
            'line_total' => 100000,
            'result_delivered_at' => $resultDeliveredAt,
        ]);

        return $order;
    }

    public function test_submit_succeeds_for_paid_and_fully_delivered_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid', now());

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Pelayanan sangat memuaskan.',
            'rating' => 5,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', $user->name);
        $response->assertJsonPath('role', 'Mahasiswa S1');
        $response->assertJsonPath('rating', 5);

        $this->assertDatabaseHas('testimonials', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'name' => $user->name,
        ]);
    }

    public function test_submit_rejected_when_result_not_yet_delivered(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid', null);

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Test',
            'rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_rejected_when_order_not_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending', now());

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Test',
            'rating' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_rejected_on_second_attempt(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid', now());

        $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Pertama',
            'rating' => 5,
        ])->assertCreated();

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Kedua',
            'rating' => 4,
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, Testimonial::where('order_id', $order->id)->count());
    }

    public function test_submit_rejected_for_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makeOrder($owner, 'paid', now());

        $response = $this->actingAs($intruder)->postJson("/api/orders/{$order->order_no}/testimonial", [
            'role' => 'Mahasiswa S1',
            'text' => 'Test',
            'rating' => 5,
        ]);

        $response->assertStatus(404);
    }

    public function test_public_endpoint_excludes_inactive_testimonials(): void
    {
        Testimonial::create(['name' => 'Aktif', 'role' => 'Alumni', 'text' => 'Bagus', 'rating' => 5, 'sort_order' => 1, 'is_active' => true]);
        Testimonial::create(['name' => 'Sembunyi', 'role' => 'Alumni', 'text' => 'Bagus', 'rating' => 5, 'sort_order' => 2, 'is_active' => false]);

        $response = $this->getJson('/api/testimonials');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Aktif'));
        $this->assertFalse($names->contains('Sembunyi'));
    }

    public function test_admin_can_toggle_visibility_but_not_edit_or_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $testimonial = Testimonial::create(['name' => 'Budi', 'role' => 'Alumni', 'text' => 'Bagus', 'rating' => 5, 'sort_order' => 1, 'is_active' => true]);

        $toggle = $this->actingAs($admin)->patchJson("/api/admin/testimonials/{$testimonial->id}/toggle-active");
        $toggle->assertOk();
        $toggle->assertJsonPath('is_active', false);

        // Same URI as the GET index route, different verb -> 405, not 404.
        $this->actingAs($admin)->postJson('/api/admin/testimonials', ['name' => 'Fake'])->assertStatus(405);
        // No route at all for these URIs anymore -> 404.
        $this->actingAs($admin)->putJson("/api/admin/testimonials/{$testimonial->id}", ['name' => 'Diedit'])->assertNotFound();
        $this->actingAs($admin)->deleteJson("/api/admin/testimonials/{$testimonial->id}")->assertNotFound();
    }

    public function test_admin_index_includes_legacy_and_real_testimonials_with_context(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid', now());

        Testimonial::create(['name' => 'Legacy', 'role' => 'Alumni', 'text' => 'Lama', 'rating' => 5, 'sort_order' => 1]);
        Testimonial::create(['name' => $user->name, 'role' => 'Mahasiswa', 'text' => 'Baru', 'rating' => 5, 'sort_order' => 2, 'user_id' => $user->id, 'order_id' => $order->id]);

        $response = $this->actingAs($admin)->getJson('/api/admin/testimonials');

        $response->assertOk();
        $data = collect($response->json('data'));

        $legacy = $data->firstWhere('name', 'Legacy');
        $real = $data->firstWhere('name', $user->name);

        $this->assertNull($legacy['order_no']);
        $this->assertSame($order->order_no, $real['order_no']);
        $this->assertSame($user->email, $real['user_email']);
    }
}
