<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescheduleInspection extends Model
{
    protected $fillable = [
        'inspection_assigne_id','accepted_inspector_id','declined_inspector_id',
        'date','time','shift','status'
    ];
}
