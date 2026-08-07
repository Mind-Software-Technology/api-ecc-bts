<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'category_id', 'title', 'description', 'flyer_path', 'location',
    'starts_at', 'ends_at', 'sort_order', 'is_active',
])]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected static function booted(): void
    {
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
