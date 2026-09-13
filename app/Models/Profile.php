<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $fillable = [
        'user_id', 'address', 'profile_img', 'phone', 
        'company_name', 'client_type',
        'license_number', 'license_expiry', 'insurance_expiry', 
        'stripe_account_id', 'stripe_customer_id', 'stripe_onboarding_completed'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inspectionTypes()
    {
        return $this->belongsToMany(
            InspectionType::class, 
            'profile_inspection_type', 
            'profile_id',
            'inspection_type_id'
        );
    }


    
}