<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionAssign extends Model
{
    protected $fillable = [
        'inspection_booking_id','inspector_id','distance',
        'estimate_time','isAssignedAdmin','isReschedule','status'
    ];
}
