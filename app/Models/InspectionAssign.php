<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAssign extends Model
{
    protected $fillable = [
        'inspection_booking_id', 'inspector_id', 'distance',
        'estimate_time', 'isAssignedAdmin', 'isReschedule', 'status'
    ];

    public function inspectionBooking(): BelongsTo
    {
        return $this->belongsTo(InspectionBooking::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report()
    {
        return $this->hasOne(InspectionReport::class);
    }

    public function inspectionReport()
    {
        return $this->hasOne(InspectionReport::class, 'inspection_assign_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }


    public function payment()
    {
        return $this->belongsTo(InspectionPayment::class, 'inspection_booking_id', 'inspection_booking_id');
    }

    public function payments()
    {
        return $this->hasMany(InspectionPayment::class, 'inspection_booking_id');
    }

    public function inspectionFeePayment()
    {
        return $this->hasOne(InspectionPayment::class, 'inspection_booking_id')
            ->where('payment_type', 'inspection_fee');
    }

    public function cancelRequest()
    {
        return $this->hasOne(CancelRequest::class, 'inspection_assign_id');
    }
}
