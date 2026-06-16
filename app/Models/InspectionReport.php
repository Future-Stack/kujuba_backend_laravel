<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'inspection_assign_id',
        'notes',
        'homeowner_feedback',
        'media',
        'report_file',
        'is_favorite',
        'status',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'media' => 'array',
        'is_favorite' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Relation: inspection assign
     */
    public function inspectionAssign()
    {
        return $this->belongsTo(InspectionAssign::class);
    }

    /**
     * Check if editable (within 48 hours)
     */
    public function isEditable()
    {
        if (!$this->started_at) {
            return true;
        }

        return now()->lessThan($this->started_at->copy()->addHours(48));
    }

    /**
     * Check if expired
     */
    public function isExpired()
    {
        if (!$this->started_at) {
            return false;
        }

        return now()->greaterThan($this->started_at->copy()->addHours(48));
    }

    /**
     * Check if completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if cancelled
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }




//     protected static function booted()
// {
//     static::updated(function ($report) {

//         // 🔥 only run when status actually changed
//         if (!$report->wasChanged('status')) {
//             return;
//         }

//         // 🔗 relation check
//         $assign = $report->inspectionAssign;

//         if (!$assign) {
//             return;
//         }

//         // 🔥 status mapping (report → assign)
//         $map = [
//             'started'   => 'inspection',
//             'completed' => 'completed',
//             'cancelled' => 'cancelled',
//         ];

//         // 🔎 get mapped status
//         $newStatus = $map[$report->status] ?? null;

//         // ❌ if no mapping found, stop
//         if (!$newStatus) {
//             return;
//         }

//         // 🔄 update assign safely
//         $assign->update([
//             'status' => $newStatus
//         ]);
//     });
// }
}