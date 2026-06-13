<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionPayment extends Model
{
    protected $fillable = [
        'inspection_booking_id','subtotal','platform_fee','total',
        'trx_id','status','urgentStatus','stripe_id','is_disbursed',
        'penalty_amount','refunded_amount'
    ];
}
