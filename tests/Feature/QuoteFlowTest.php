<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, string $status, int $itemCount = 1): Order
    {
        $order = Order::create([
            'order_no' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'guest_name' => $user->name,
            'guest_email' => $user->email,
            'guest_phone' => '081234567890',
            'status' => $status,
            'subtotal' => null,
            'total' => null,
        ]);

        for ($i = 0; $i < $itemCount; $i++) {
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
                'title' => 'Layanan Test '.$i,
                'tagline' => 'Tagline',
                'description' => 'Description',
                'points' => [],
                'icon' => 'icon',
                'accent' => 'blue',
                'price' => 100000,
                'is_active' => true,
                'requires_attachment' => false,
            ]);

            $order->items()->create([
                'service_id' => $service->id,
                'title_snapshot' => $service->title,
                'qty' => 2,
                'price_snapshot' => null,
                'line_total' => null,
            ]);
        }

        return $order;
    }

    public function test_admin_can_quote_order_then_customer_can_accept(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'awaiting_quote', itemCount: 2);
        $items = $order->items;

        $quoteResponse = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->order_no}/quote", [
            'items' => [
                ['id' => $items[0]->id, 'price' => 50000],
                ['id' => $items[1]->id, 'price' => 75000],
            ],
        ]);

        $quoteResponse->assertOk();
        $quoteResponse->assertJsonPath('status', 'quoted');
        // qty=2 per item -> line_total = price * 2
        $quoteResponse->assertJsonPath('total', (50000 * 2) + (75000 * 2));

        $acceptResponse = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/accept-quote");
        $acceptResponse->assertOk();
        $acceptResponse->assertJsonPath('status', 'pending');
    }

    public function test_admin_quote_rejects_partial_pricing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'awaiting_quote', itemCount: 2);
        $items = $order->items;

        $response = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->order_no}/quote", [
            'items' => [
                ['id' => $items[0]->id, 'price' => 50000],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame('awaiting_quote', $order->fresh()->status);
    }

    public function test_admin_quote_rejects_unknown_item_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'awaiting_quote', itemCount: 1);

        $response = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->order_no}/quote", [
            'items' => [
                ['id' => 999999, 'price' => 50000],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_quote_rejects_wrong_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending', itemCount: 1);
        $item = $order->items->first();

        $response = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->order_no}/quote", [
            'items' => [
                ['id' => $item->id, 'price' => 50000],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_requote_before_customer_accepts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'quoted', itemCount: 1);
        $item = $order->items->first();

        $response = $this->actingAs($admin)->postJson("/api/admin/orders/{$order->order_no}/quote", [
            'items' => [
                ['id' => $item->id, 'price' => 60000],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'quoted');
        $response->assertJsonPath('total', 120000);
    }

    public function test_customer_cannot_accept_before_quoted(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'awaiting_quote');

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/accept-quote");

        $response->assertStatus(422);
        $this->assertSame('awaiting_quote', $order->fresh()->status);
    }

    public function test_customer_can_decline_awaiting_quote_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'awaiting_quote');

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/decline");

        $response->assertOk();
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_customer_can_decline_quoted_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'quoted');

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/decline");

        $response->assertOk();
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_customer_cannot_decline_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'pending');

        $response = $this->actingAs($user)->postJson("/api/orders/{$order->order_no}/decline");

        $response->assertStatus(422);
    }

    public function test_accept_and_decline_require_ownership(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $order = $this->makeOrder($owner, 'quoted');

        $this->actingAs($intruder)->postJson("/api/orders/{$order->order_no}/accept-quote")->assertStatus(404);
        $this->actingAs($intruder)->postJson("/api/orders/{$order->order_no}/decline")->assertStatus(404);
    }

    public function test_admin_can_view_customer_attachment_and_result_files(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $attachmentPath = 'order-attachments/naskah-test.pdf';
        $resultPath = 'order-results/hasil-test.pdf';
        Storage::disk('local')->put($attachmentPath, 'naskah content');
        Storage::disk('local')->put($resultPath, 'hasil content');
        $item->update([
            'attachment_path' => $attachmentPath,
            'attachment_original_name' => 'naskah.pdf',
            'result_path' => $resultPath,
            'result_original_name' => 'hasil.pdf',
        ]);

        $this->actingAs($admin)->get("/api/admin/orders/{$order->order_no}/items/{$item->id}/attachment")->assertOk();
        $this->actingAs($admin)->get("/api/admin/orders/{$order->order_no}/items/{$item->id}/result")->assertOk();
    }

    public function test_non_admin_cannot_view_order_files(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 'paid');
        $item = $order->items->first();

        $this->actingAs($user)->get("/api/admin/orders/{$order->order_no}/items/{$item->id}/attachment")->assertForbidden();
    }
}
