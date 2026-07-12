<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'role', 'text', 'rating', 'sort_order'])]
class Testimonial extends Model
{
    //
}
