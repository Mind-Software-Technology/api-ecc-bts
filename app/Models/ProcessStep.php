<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['step_number', 'icon', 'title', 'description', 'sort_order'])]
class ProcessStep extends Model
{
    //
}
