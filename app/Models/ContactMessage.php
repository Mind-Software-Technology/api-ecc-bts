<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'email', 'service_id', 'message'])]
class ContactMessage extends Model
{
    public $timestamps = false;

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
