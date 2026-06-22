<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectorPayout extends Model
{
    protected $fillable = [
        'inspector_id',
        'inspection_assign_id',
        'inspection_payment_id',
        'amount',
        'platform_fee',
        'currency',
        'status',
        'payment_type',
        'method',
        'transaction_id',
        'stripe_transfer_id',
        'is_disbursed',
        'calculated_at',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'admin_cut' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'is_disbursed' => 'boolean',
        'calculated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Inspector (User)
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * Inspection Assign
     */
    public function inspectionAssign(): BelongsTo
    {
        return $this->belongsTo(InspectionAssign::class);
    }

    /**
     * Customer Payment
     */
    public function inspectionPayment(): BelongsTo
    {
        return $this->belongsTo(InspectionPayment::class);
    }
}