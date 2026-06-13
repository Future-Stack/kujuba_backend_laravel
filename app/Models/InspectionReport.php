<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReport extends Model
{
    protected $fillable = ['inspection_assign_id','galleries','report_file','feedback','status'];
}
