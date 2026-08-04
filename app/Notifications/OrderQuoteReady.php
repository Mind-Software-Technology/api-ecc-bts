<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderQuoteReady extends Notification
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
        $total = 'Rp '.number_format($this->order->total, 0, ',', '.');

        return (new MailMessage)
            ->subject("Penawaran harga untuk pesanan {$this->order->order_no} sudah siap")
            ->greeting("Halo {$this->order->guest_name},")
            ->line("Tim kami telah menetapkan harga untuk pesanan {$this->order->order_no} dengan total {$total}.")
            ->line('Silakan tinjau rincian harga dan pilih untuk melanjutkan pembayaran atau membatalkan permintaan.')
            ->action('Lihat Penawaran', rtrim(config('services.frontend_url'), '/').'/riwayat-pembayaran')
            ->line('Terima kasih telah menggunakan layanan ECC-BTS.');
    }
}
