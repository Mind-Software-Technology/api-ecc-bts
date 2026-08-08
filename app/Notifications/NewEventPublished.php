<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Diumumkan ke semua pelanggan saat admin mempublikasikan kegiatan baru.
 *
 * Satu-satunya notifikasi yang di-queue: yang lain menyasar satu orang,
 * yang ini menyebar ke seluruh pelanggan — mengirimnya inline berarti admin
 * menunggu satu request HTTPS ke layanan push per perangkat yang berlangganan.
 * Konsekuensinya butuh worker antrian; lihat catatan di README/TODO.
 *
 * Sengaja tanpa channel 'mail': mengirim email ke seluruh pelanggan setiap
 * kali ada kegiatan baru itu spam, bukan notifikasi.
 */
class NewEventPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Event $event,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_event',
            'message' => $this->message(),
            'url' => '/kegiatan',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Kegiatan Baru — ECC-BTS')
            ->body($this->message())
            ->icon('/images/logo.png')
            // `tag` per kegiatan: notifikasi ulang untuk kegiatan yang sama
            // menggantikan popup lama, bukan menumpuk.
            ->tag("event-{$this->event->id}")
            ->data(['url' => '/kegiatan']);
    }

    private function message(): string
    {
        return "Kegiatan baru: \"{$this->event->title}\" — lihat detail dan jadwalnya.";
    }
}
