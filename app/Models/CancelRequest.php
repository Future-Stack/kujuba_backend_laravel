<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancelRequest extends Model
{
    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(InspectionBooking::class, 'inspection_booking_id');
    }

    public function assign()
    {
        return $this->belongsTo(InspectionAssign::class, 'inspection_assign_id');
    }
}
