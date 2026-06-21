<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionPayment extends Model
{
    protected $table = 'inspection_payments';

    /**
     * 🔥 Mass Assignable Fields
     */
    protected $fillable = [
        'inspection_booking_id',

        'subtotal',
        'platform_fee',
        'urgent_fee',

        'inspector_share',
        'admin_share',

        'total',

        'payment_type',
        'trx_id',
        'status',
        'urgentStatus',

        'stripe_id',
        'is_disbursed',

        'penalty_amount',
        'refunded_amount',
        'paycut_amount',

        'payout_status',
    ];

    /**
     * 🔥 Casts (VERY IMPORTANT for money + boolean)
     */
    protected $casts = [
        'subtotal' => 'float',
        'platform_fee' => 'float',
        'urgent_fee' => 'float',

        'inspector_share' => 'float',
        'admin_share' => 'float',

        'total' => 'float',

        'is_disbursed' => 'boolean',

        'penalty_amount' => 'float',
        'refunded_amount' => 'float',
        'paycut_amount' => 'float',
    ];

    /**
     * 🔗 Relationships
     */

    // Payment belongs to booking
    public function inspectionBooking()
    {
        return $this->belongsTo(InspectionBooking::class, 'inspection_booking_id');
    }
}