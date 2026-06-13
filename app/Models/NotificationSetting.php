<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = ['type','title','message','sender_id','sent_to','status','sent_at'];
}
