<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewQuoteRequest extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
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
        $itemCount = $this->order->items->count();

        return (new MailMessage)
            ->subject("Permintaan harga baru: {$this->order->order_no}")
            ->greeting('Halo Admin,')
            ->line("Ada permintaan harga baru dari {$this->order->guest_name} pada pesanan {$this->order->order_no} ({$itemCount} layanan).")
            ->line('Silakan tinjau file yang diunggah dan tentukan harga.')
            ->action('Lihat Pesanan', rtrim(config('services.frontend_url'), '/')."/admin/orders/{$this->order->order_no}")
            ->line('Terima kasih.');
    }
}
