<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = [
        'platform_name','support_mail','max_inspector_area',
        'inspector_response_time','urgent_booking_lead','report_deadline',
        'platform_commission','auto_approve','urgent_inspection_fee',
        'late_cancellation_penalty','last_minute_cancel_penalty'
    ];
}
