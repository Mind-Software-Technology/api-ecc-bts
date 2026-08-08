<?php

namespace App\Models;

use App\Notifications\NewEventPublished;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id', 'title', 'description', 'flyer_path', 'location',
    'starts_at', 'ends_at', 'sort_order', 'is_active',
])]
// notified_at sengaja di luar Fillable — diisi sistem, bukan form admin.
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'notified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
        // Diumumkan sekali seumur hidup kegiatan, saat pertama kali aktif —
        // `saved` (bukan `created`) supaya alur "simpan sebagai draf lalu
        // aktifkan" juga ikut terkirim, dan `notified_at` menjaga agar setiap
        // penyuntingan berikutnya tidak mengirim ulang.
        static::saved(function (Event $event) {
            if (! $event->is_active || $event->notified_at) {
                return;
            }

            $event->forceFill(['notified_at' => now()])->saveQuietly();

            Notification::send(
                User::where('role', 'user')->get(),
                new NewEventPublished($event),
            );
        });

        static::updating(function (Event $event) {
            if ($event->isDirty('flyer_path') && $event->getOriginal('flyer_path')) {
                Storage::disk('public')->delete($event->getOriginal('flyer_path'));
            }
        });

        static::deleting(function (Event $event) {
            if ($event->flyer_path) {
                Storage::disk('public')->delete($event->flyer_path);
            }
        });
    }
}
