<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderQuoteReady extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
    ) {}

    /**
     * ponytail: sengaja tidak ShouldQueue. Menyasar satu pelanggan, jadi
     * biayanya kecil, dan tetap sinkron berarti email penawaran tidak
     * bergantung pada worker antrian yang hidup di shared hosting.
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
            'type' => 'order_quoted',
            'message' => $this->message(),
            'url' => '/riwayat-pembayaran',
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Penawaran Harga Siap — ECC-BTS')
            ->body($this->message())
            ->icon('/images/logo.png')
            ->tag("order-{$this->order->order_no}")
            ->data(['url' => '/riwayat-pembayaran']);
    }

    private function message(): string
    {
        $total = 'Rp '.number_format($this->order->total, 0, ',', '.');

        return "Pesanan {$this->order->order_no} sudah diberi harga {$total} — silakan lanjutkan pembayaran.";
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
