<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderResultReady extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly OrderItem $orderItem,
        private readonly bool $isRevision = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
