<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionBooking extends Model
{
    protected $table = 'inspection_bookings';

    protected $fillable = [
        'homeowner_id', 
        'property_address', 
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

    public function payment()
    {
        return $this->hasOne(InspectionPayment::class, 'inspection_booking_id');
    }
    public function user()
{
    return $this->belongsTo(User::class);
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

    protected $casts = [
        'booking_date'   => 'date',
        'scheduled_date' => 'date',
    ];
    
}