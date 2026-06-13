<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionBooking extends Model
{
    protected $fillable = [
        'homeowner_id','property_address','property_type','property_size',
        'note','property_img','booking_date','scheduled_date','scheduled_time',
        'scheduled_shift','urgent_status','status','latitude','longitude','isRescheduled'
    ];
}
