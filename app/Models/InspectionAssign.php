<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAssign extends Model
{
    protected $fillable = [
        'inspection_booking_id','inspector_id','distance',
        'estimate_time','isAssignedAdmin','isReschedule','status'
    ];

    public function inspectionBooking() :BelongsTo
    {
        return $this->belongsTo(InspectionBooking::class);
    }

    public function inspector() :BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
