<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\TestimonialResource\Pages\ListTestimonials;
use App\Models\Advantage;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProcessStep;
use App\Models\Service;
use App\Models\SiteConfig;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixtures(): array
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
            'points' => ['poin 1'],
            'icon' => 'icon',
            'accent' => 'blue',
            'price' => 100000,
            'is_active' => true,
            'requires_attachment' => false,
        ]);

        Event::create([
            'category_id' => $category->id,
            'title' => 'Event Test',
            'description' => 'Description',
            'location' => 'Jakarta',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        Faq::create(['question' => 'Q?', 'answer' => 'A.', 'sort_order' => 0]);
        Advantage::create(['icon' => 'icon', 'title' => 'Adv', 'description' => 'Desc', 'sort_order' => 0]);
        ProcessStep::create(['step_number' => 1, 'icon' => 'icon', 'title' => 'Step', 'description' => 'Desc', 'sort_order' => 0]);
        ContactMessage::create(['name' => 'Budi', 'email' => 'budi@example.com', 'service_id' => $service->id, 'message' => 'Halo']);

        $customer = User::factory()->create(['role' => 'user']);

        $order = Order::create([
            'order_no' => 'ORD-'.uniqid(),
            'user_id' => $customer->id,
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '081234567890',
            'status' => 'awaiting_quote',
            'subtotal' => 0,
            'total' => 0,
        ]);
        $orderItem = $order->items()->create([
            'service_id' => $service->id,
            'title_snapshot' => $service->title,
            'qty' => 1,
            'price_snapshot' => 0,
            'line_total' => 0,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'midtrans_order_id' => $order->order_no,
            'transaction_id' => 'trx-'.uniqid(),
            'payment_type' => 'bank_transfer',
            'gross_amount' => 100000,
            'transaction_status' => 'pending',
        ]);

        Testimonial::create([
            'name' => 'Budi',
            'role' => 'Mahasiswa',
            'text' => 'Mantap',
            'rating' => 5,
            'sort_order' => 0,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'is_active' => true,
        ]);

        SiteConfig::create([
            'brand_name' => 'ECC BTS',
            'social_links' => [],
            'nav_items' => [],
        ]);

        return compact('order', 'orderItem');
    }

    public function test_admin_can_load_every_panel_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ['order' => $order] = $this->seedFixtures();

        $pages = [
            '/admin',
            '/admin/categories',
            '/admin/faqs',
            '/admin/advantages',
            '/admin/process-steps',
            '/admin/services',
            '/admin/events',
            '/admin/testimonials',
            '/admin/contact-messages',
            '/admin/payments',
            '/admin/manage-site-config',
            '/admin/orders',
            "/admin/orders/{$order->id}",
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_non_admin_cannot_access_panel(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_set_quote_price_via_order_view_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ['order' => $order, 'orderItem' => $orderItem] = $this->seedFixtures();

        $this->actingAs($admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
            ->callAction('quote', data: [
                'prices' => [$orderItem->id => 150000],
            ])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame('quoted', $order->status);
        $this->assertSame(150000, $order->total);
    }

    public function test_admin_can_toggle_testimonial_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedFixtures();
        $testimonial = Testimonial::first();
        $wasActive = (bool) $testimonial->is_active;

        $this->actingAs($admin);

        Livewire::test(ListTestimonials::class)
            ->callTableAction('toggleActive', $testimonial)
            ->assertHasNoTableActionErrors();

        $this->assertSame(! $wasActive, (bool) $testimonial->fresh()->is_active);
    }
}
