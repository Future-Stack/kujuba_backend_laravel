<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientReportSchedule extends Model
{
    use HasFactory;

    protected $table = 'client_report_schedules';

    protected $fillable = [
        'client_id',
        'recipient_email',
        'frequency',
        'send_time',
        'day_of_week',
        'day_of_month',
        'inspection_status',
        'is_enabled',
        'timezone',
        'last_sent_at',
        'created_by',
    ];

    protected $casts = [
        'is_enabled'   => 'boolean',
        'last_sent_at' => 'datetime',
        'day_of_month' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
