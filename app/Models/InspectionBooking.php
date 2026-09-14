<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionBooking extends Model
{
    protected $table = 'inspection_bookings';

    protected $fillable = [
        'homeowner_id',
        'client_id',
        'property_address',
        'zip_code',
        'property_type',
        'property_size',
        'note',
        'property_img',
        'booking_date',
        'scheduled_date',
        'scheduled_time',
        'scheduled_shift',
        'urgent_status',
        'status',
        'latitude',
        'longitude',
        'isRescheduled'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function payment()
    {
        return $this->hasOne(InspectionPayment::class, 'inspection_booking_id');
    }

    public function inspectionFeePayment()
    {
        return $this->hasOne(InspectionPayment::class, 'inspection_booking_id')
            ->where('payment_type', 'inspection_fee');
    }

    // Multiple payments per booking
    public function payments()
    {
        return $this->hasMany(InspectionPayment::class, 'inspection_booking_id');
    }

    /**
     * 👤 HOMEOWNER relation (FIXED)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'homeowner_id');
    }

    public function inspectionAssign()
    {
        return $this->hasOne(InspectionAssign::class, 'inspection_booking_id');
    }

    public function inspectionTypes()
    {
        return $this->belongsToMany(
            InspectionType::class,
            'booking_inspection_type',
            'inspection_booking_id',
            'inspection_type_id'
        );
    }

    public function assignment()
    {
        return $this->hasOne(InspectionAssign::class, 'inspection_booking_id', 'id');
    }

    public function declines()
    {
        return $this->hasMany(DeclineInspection::class);
    }

    public function reschedule()
    {
        return $this->hasOne(RescheduleInspection::class);
    }

    public function homeowner()
    {
        return $this->belongsTo(User::class, 'homeowner_id');
    }
    public function cancelRequest()
    {
        return $this->hasOne(CancelRequest::class, 'inspection_booking_id');
    }

    // Media files (images, attachments)



    protected $casts = [
        'booking_date' => 'date',
        'scheduled_date' => 'date',
    ];

}
