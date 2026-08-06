<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'category_id', 'sort_order', 'title', 'tagline', 'description', 'points',
    'icon', 'accent', 'image_url', 'image_path', 'image_alt', 'price', 'rating',
    'reviews_count', 'badge', 'is_active', 'requires_attachment',
])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'points' => 'array',
            'is_active' => 'boolean',
            'requires_attachment' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Service $service) {
            if ($service->isDirty('image_path') && $service->getOriginal('image_path')) {
                Storage::disk('public')->delete($service->getOriginal('image_path'));
            }
        });

        static::deleting(function (Service $service) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }
}
