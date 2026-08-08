<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewEventPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(): Category
    {
        return Category::create([
            'slug' => 'kategori-'.uniqid(),
            'title' => 'Kategori Test',
            'short_desc' => 'Short desc',
            'description' => 'Description',
            'icon' => 'icon',
            'accent' => 'blue',
        ]);
    }

    private function makeEvent(bool $isActive): Event
    {
        return Event::create([
            'category_id' => $this->makeCategory()->id,
            'title' => 'Workshop Statistik',
            'description' => 'Description',
            'location' => 'Jakarta',
            'sort_order' => 0,
            'is_active' => $isActive,
        ]);
    }

    public function test_publishing_an_event_notifies_every_customer_but_not_admins(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->makeEvent(isActive: true);

        Notification::assertSentTo($customer, NewEventPublished::class);
        Notification::assertNotSentTo($admin, NewEventPublished::class);
    }

    public function test_an_inactive_event_notifies_nobody_until_it_is_activated(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'user']);
        $event = $this->makeEvent(isActive: false);

        Notification::assertNothingSent();

        // Alur "simpan sebagai draf lalu aktifkan" harus tetap mengumumkan.
        $event->update(['is_active' => true]);

        Notification::assertSentTo($customer, NewEventPublished::class);
    }

    public function test_editing_a_published_event_does_not_notify_again(): void
    {
        Notification::fake();

        User::factory()->create(['role' => 'user']);
        $event = $this->makeEvent(isActive: true);

        Notification::assertSentTimes(NewEventPublished::class, 1);

        $event->update(['title' => 'Judul diperbarui']);
        $event->update(['location' => 'Bandung']);

        Notification::assertSentTimes(NewEventPublished::class, 1);
    }

    public function test_event_notification_goes_to_both_the_bell_and_web_push(): void
    {
        $event = $this->makeEvent(isActive: true);
        $notification = new NewEventPublished($event);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertSame(['database', WebPushChannel::class], $notification->via($user));
        $this->assertSame('new_event', $notification->toDatabase($user)['type']);
        $this->assertStringContainsString('Workshop Statistik', $notification->toDatabase($user)['message']);

        // Bentuk payload ini persis yang dibaca public/sw.js di frontend —
        // `tag` harus ikut terkirim (setter tag(), bukan options(), yang cuma
        // opsi protokol TTL/urgency dan tidak pernah sampai ke browser).
        $payload = $notification->toWebPush($user, $notification)->toArray();
        $this->assertSame("event-{$event->id}", $payload['tag']);
        $this->assertSame('/kegiatan', $payload['data']['url']);
        $this->assertNotEmpty($payload['title']);
        $this->assertNotEmpty($payload['body']);
    }

    public function test_customer_can_register_and_remove_a_push_subscription(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $payload = [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-token-value'],
        ];

        $this->actingAs($user)->postJson('/api/push-subscriptions', $payload)->assertNoContent();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        // Berlangganan ulang dari browser yang sama tidak boleh menggandakan.
        $this->actingAs($user)->postJson('/api/push-subscriptions', $payload)->assertNoContent();
        $this->assertSame(1, $user->fresh()->pushSubscriptions()->count());

        $this->actingAs($user)
            ->deleteJson('/api/push-subscriptions', ['endpoint' => $endpoint])
            ->assertNoContent();
        $this->assertSame(0, $user->fresh()->pushSubscriptions()->count());
    }

    public function test_push_subscription_requires_login(): void
    {
        $this->postJson('/api/push-subscriptions', [
            'endpoint' => 'https://example.com/push/1',
            'keys' => ['p256dh' => 'k', 'auth' => 'a'],
        ])->assertUnauthorized();
    }

    public function test_bell_lists_the_customers_notifications_and_marks_them_read(): void
    {
        $customer = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);

        $this->makeEvent(isActive: true);

        $response = $this->actingAs($customer)->getJson('/api/notifications')->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonPath('data.0.type', 'new_event');
        $response->assertJsonPath('data.0.url', '/kegiatan');
        $response->assertJsonPath('data.0.read', false);

        // Notifikasi milik orang lain tidak boleh bocor lintas akun.
        $this->assertCount(1, $other->notifications);

        $this->actingAs($customer)->postJson('/api/notifications/read')->assertNoContent();
        $this->actingAs($customer)->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('data.0.read', true);

        // Menandai baca milik satu akun tidak menyentuh akun lain.
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }
}
