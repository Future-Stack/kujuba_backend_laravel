<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['rating','homeowner_id','inspection_assign_id','description'];
}
