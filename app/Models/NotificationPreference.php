<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id','new_inspection','upcoming','reschedule','cancellation','email'];
}
