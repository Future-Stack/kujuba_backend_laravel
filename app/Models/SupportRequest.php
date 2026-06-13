<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = ['user_id','title','explanation','status','reply'];
}
