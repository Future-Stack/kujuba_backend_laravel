<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeclineInspection extends Model
{
    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(InspectionBooking::class);
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
