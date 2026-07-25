<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(int $price = 100000): Service
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
        ]);
    }

    public function test_guest_can_add_update_and_checkout_cart(): void
    {
        $service = $this->makeService(100000);

        $addResponse = $this->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 2]);
        $addResponse->assertCreated(); // first add creates the guest cart -> 201 (wasRecentlyCreated)
        $sessionId = $addResponse->headers->get('X-Session-Id');
        $this->assertNotEmpty($sessionId);
        $itemId = $addResponse->json('items.0.id');

        $updateResponse = $this->patchJson("/api/cart/items/{$itemId}", ['qty' => 3], ['X-Session-Id' => $sessionId]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('items.0.qty', 3);
        $updateResponse->assertJsonPath('total', 300000);

        $checkoutResponse = $this->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
        ], ['X-Session-Id' => $sessionId]);

        $checkoutResponse->assertCreated();
        $checkoutResponse->assertJsonPath('subtotal', 300000);
        $checkoutResponse->assertJsonPath('total', 300000);
        $checkoutResponse->assertJsonPath('items.0.qty', 3);
        $checkoutResponse->assertJsonPath('items.0.price_snapshot', 100000);

        $cartAfter = $this->getJson('/api/cart', ['X-Session-Id' => $sessionId]);
        $cartAfter->assertOk();
        $this->assertCount(0, $cartAfter->json('items'));
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $response = $this->postJson('/api/orders', [
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
        ], ['X-Session-Id' => 'session-without-cart']);

        $response->assertStatus(422);
    }

    public function test_cart_items_are_isolated_between_sessions(): void
    {
        $service = $this->makeService();

        $addA = $this->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1], ['X-Session-Id' => 'session-a']);
        $addA->assertCreated();
        $itemAId = $addA->json('items.0.id');

        $addB = $this->postJson('/api/cart/items', ['service_id' => $service->id, 'qty' => 1], ['X-Session-Id' => 'session-b']);
        $addB->assertCreated();

        $crossAccess = $this->patchJson("/api/cart/items/{$itemAId}", ['qty' => 5], ['X-Session-Id' => 'session-b']);
        $crossAccess->assertStatus(404);
    }
}
