<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionPayment extends Model
{
    protected $table = 'inspection_payments';

    protected $fillable = [
        'inspection_booking_id', 'subtotal', 'platform_fee', 'total',
        'trx_id', 'status', 'urgentStatus', 'stripe_id',
        'is_disbursed', 'penalty_amount', 'refunded_amount', 'urgent_fee', 'inspector_share', 'admin_share','payout_status'
    ];





    public function inspectionBooking()
{
    return $this->belongsTo(InspectionBooking::class, 'inspection_booking_id');
}
}


