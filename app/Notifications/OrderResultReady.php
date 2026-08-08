<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderResultReady extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly OrderItem $orderItem,
        private readonly bool $isRevision = false,
    ) {}

    /**
     * ponytail: sengaja tidak ShouldQueue — alasan sama dengan OrderQuoteReady.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_result',
            'message' => $this->message(),
            'url' => '/riwayat-pembayaran',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->isRevision ? 'Hasil Diperbarui — ECC-BTS' : 'Hasil Siap — ECC-BTS')
            ->body($this->message())
            ->icon('/images/logo.png')
            ->tag("result-{$this->orderItem->id}")
            ->data(['url' => '/riwayat-pembayaran']);
    }

    private function message(): string
    {
        return $this->isRevision
            ? "Hasil untuk \"{$this->orderItem->title_snapshot}\" pada pesanan {$this->order->order_no} telah diperbarui."
            : "Hasil untuk \"{$this->orderItem->title_snapshot}\" pada pesanan {$this->order->order_no} sudah siap — silakan unduh.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->isRevision
            ? "Hasil layanan \"{$this->orderItem->title_snapshot}\" telah diperbarui"
            : "Hasil layanan \"{$this->orderItem->title_snapshot}\" sudah siap";

        $line = $this->isRevision
            ? "Hasil untuk layanan \"{$this->orderItem->title_snapshot}\" pada pesanan {$this->order->order_no} telah diperbarui."
            : "Hasil untuk layanan \"{$this->orderItem->title_snapshot}\" pada pesanan {$this->order->order_no} sudah tersedia.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Halo {$this->order->guest_name},")
            ->line($line)
            ->action('Lihat Pesanan', rtrim(config('services.frontend_url'), '/').'/riwayat-pembayaran')
            ->line('Terima kasih telah menggunakan layanan ECC-BTS.');
    }
}
