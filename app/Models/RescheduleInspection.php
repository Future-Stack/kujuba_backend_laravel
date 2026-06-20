<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RescheduleInspection extends Model
{
    protected $fillable = [
        'inspection_assign_id','inspection_booking_id','accepted_inspector_id','declined_inspector_id',
        'date','time','shift','status'
    ];
}
