<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'short_desc', 'description', 'icon', 'accent', 'sort_order'])]
class Category extends Model
{
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
