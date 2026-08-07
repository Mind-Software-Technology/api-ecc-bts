<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewQuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(int $price = 100000, bool $requiresAttachment = true): Service
    {
        $category = Category::create([
            'slug' => 'kategori-'.uniqid(),
            'title' => 'Kategori Test',
            'short_desc' => 'Short desc',
            'description' => 'Description',
            'icon' => 'icon',
            'accent' => 'blue',
        ]);

        return Service::create([
            'slug' => 'layanan-'.uniqid(),
            'category_id' => $category->id,
            'title' => 'Layanan Test',
            'tagline' => 'Tagline',
            'description' => 'Description',
            'points' => ['point 1'],
            'icon' => 'icon',
            'accent' => 'blue',
            'price' => $price,
            'is_active' => true,
            'requires_attachment' => $requiresAttachment,
        ]);
    }

    public function test_cart_and_checkout_require_login(): void
    {
        $service = $this->makeService();

        $this->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1])->assertUnauthorized();
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }

    public function test_logged_in_user_can_add_update_and_checkout_cart(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $service = $this->makeService(100000);

        $addResponse = $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 2]);
        $addResponse->assertCreated();
        $itemId = $addResponse->json('items.0.id');

        $updateResponse = $this->actingAs($user)->patchJson("/api/cart/items/{$itemId}", ['qty' => 3]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('items.0.qty', 3);
        $updateResponse->assertJsonPath('total', 300000);

        $checkoutResponse = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('status', 'awaiting_quote');
        $checkoutResponse->assertJsonPath('subtotal', null);
        $checkoutResponse->assertJsonPath('total', null);
        $checkoutResponse->assertJsonPath('items.0.qty', 3);
        $checkoutResponse->assertJsonPath('items.0.price_snapshot', null);
        $checkoutResponse->assertJsonPath('items.0.has_attachment', false);
        $checkoutResponse->assertJsonPath('guest_email', $user->email);

        $cartAfter = $this->actingAs($user)->getJson('/api/cart');
        $cartAfter->assertOk();
        $this->assertCount(0, $cartAfter->json('items'));
    }

    public function test_checkout_notifies_admins_only(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create();
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: false);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->assertCreated();

        Notification::assertSentTo($admin, NewQuoteRequest::class);
        Notification::assertNotSentTo($otherUser, NewQuoteRequest::class);
        Notification::assertNotSentTo($user, NewQuoteRequest::class);
    }

    public function test_checkout_succeeds_without_attachment_when_not_required(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: false);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ]);

        $response->assertCreated();
    }

    public function test_checkout_succeeds_without_attachment_even_when_service_requires_it(): void
    {
        // Attachment upload is a separate step now (after the WhatsApp
        // consultation) — checkout itself never needs a file, regardless of
        // requires_attachment.
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: true);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('items.0.requires_attachment', true);
        $response->assertJsonPath('items.0.has_attachment', false);
    }

    public function test_customer_can_upload_attachment_after_order_created(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: true);
        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        $itemId = $order['items'][0]['id'];

        $response = $this->actingAs($user)->post("/api/orders/{$order['order_no']}/items/{$itemId}/attachment", [
            'attachment' => UploadedFile::fake()->create('naskah.pdf', 500, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('has_attachment', true);
        $response->assertJsonPath('attachment_original_name', 'naskah.pdf');
    }

    public function test_attachment_upload_requires_a_file(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService();
        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        $itemId = $order['items'][0]['id'];

        $response = $this->actingAs($user)->postJson("/api/orders/{$order['order_no']}/items/{$itemId}/attachment", []);

        $response->assertStatus(422);
    }

    public function test_attachment_upload_replaces_previous_file_on_revision(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $service = $this->makeService();
        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        $itemId = $order['items'][0]['id'];
        $uploadUrl = "/api/orders/{$order['order_no']}/items/{$itemId}/attachment";

        $this->actingAs($user)->post($uploadUrl, [
            'attachment' => UploadedFile::fake()->create('pertama.pdf', 100, 'application/pdf'),
        ])->assertOk();
        $firstPath = \App\Models\OrderItem::find($itemId)->attachment_path;
        Storage::disk('local')->assertExists($firstPath);

        $response = $this->actingAs($user)->post($uploadUrl, [
            'attachment' => UploadedFile::fake()->create('kedua.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('attachment_original_name', 'kedua.pdf');
        Storage::disk('local')->assertMissing($firstPath);
    }

    public function test_attachment_upload_requires_ownership(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $service = $this->makeService();
        $this->actingAs($owner)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($owner)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        $itemId = $order['items'][0]['id'];

        $response = $this->actingAs($intruder)->post("/api/orders/{$order['order_no']}/items/{$itemId}/attachment", [
            'attachment' => UploadedFile::fake()->create('naskah.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(404);
    }

    public function test_attachment_upload_rejected_once_order_is_pending(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $service = $this->makeService();
        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        $itemId = $order['items'][0]['id'];
        Order::where('order_no', $order['order_no'])->update(['status' => 'pending']);

        $response = $this->actingAs($user)->post("/api/orders/{$order['order_no']}/items/{$itemId}/attachment", [
            'attachment' => UploadedFile::fake()->create('naskah.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ]);

        $response->assertStatus(422);
    }

    public function test_orderer_data_can_be_updated_before_payment(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: false);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();

        $response = $this->actingAs($user)->patchJson("/api/orders/{$order['order_no']}", [
            'guest_name' => 'Budi Santoso',
        ]);

        $response->assertOk();
        $response->assertJsonPath('guest_name', 'Budi Santoso');
    }

    public function test_orderer_data_cannot_be_updated_once_paid(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: false);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $order = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ])->json();
        Order::whereKey($order['id'])->update(['status' => 'paid']);

        $response = $this->actingAs($user)->patchJson("/api/orders/{$order['order_no']}", [
            'guest_name' => 'Budi Santoso',
            'guest_phone' => '089876543210',
        ]);

        $response->assertStatus(422);
    }

    public function test_cart_items_are_isolated_between_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $service = $this->makeService();

        $addA = $this->actingAs($userA)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $addA->assertCreated();
        $itemAId = $addA->json('items.0.id');

        $addB = $this->actingAs($userB)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);
        $addB->assertCreated();

        $crossAccess = $this->actingAs($userB)->patchJson("/api/cart/items/{$itemAId}", ['qty' => 5]);
        $crossAccess->assertStatus(404);
    }
}
