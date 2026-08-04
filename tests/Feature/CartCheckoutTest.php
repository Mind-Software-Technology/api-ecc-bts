<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $checkoutResponse = $this->actingAs($user)->post('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
            'attachments' => [$service->id => UploadedFile::fake()->create('naskah.pdf', 500, 'application/pdf')],
        ]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('subtotal', 300000);
        $checkoutResponse->assertJsonPath('total', 300000);
        $checkoutResponse->assertJsonPath('items.0.qty', 3);
        $checkoutResponse->assertJsonPath('items.0.price_snapshot', 100000);
        $checkoutResponse->assertJsonPath('items.0.attachment_original_name', 'naskah.pdf');
        $checkoutResponse->assertJsonPath('guest_email', $user->email);

        $cartAfter = $this->actingAs($user)->getJson('/api/cart');
        $cartAfter->assertOk();
        $this->assertCount(0, $cartAfter->json('items'));
    }

    public function test_checkout_fails_without_attachment_when_required(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService(requiresAttachment: true);

        $this->actingAs($user)->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_phone' => '081234567890',
        ]);

        $response->assertStatus(422);
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
            'guest_phone' => '089876543210',
        ]);

        $response->assertOk();
        $response->assertJsonPath('guest_name', 'Budi Santoso');
        $response->assertJsonPath('guest_phone', '089876543210');
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
