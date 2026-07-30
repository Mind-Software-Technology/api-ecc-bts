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
        return (new MailMessage)
            ->subject("Hasil layanan \"{$this->orderItem->title_snapshot}\" sudah siap")
            ->greeting("Halo {$this->order->guest_name},")
            ->line("Hasil untuk layanan \"{$this->orderItem->title_snapshot}\" pada pesanan {$this->order->order_no} sudah tersedia.")
            ->action('Lihat Pesanan', rtrim(config('services.frontend_url'), '/')."/pesanan/{$this->order->order_no}")
            ->line('Terima kasih telah menggunakan layanan ECC-BTS.');
    }
}
