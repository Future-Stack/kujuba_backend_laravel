<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'img',
        'title',
        'short_desc',
        'price',
        'status'
    ];
}