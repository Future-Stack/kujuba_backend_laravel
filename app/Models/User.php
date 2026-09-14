<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'otp',
        'otp_expire_at',
        'status',
        'user_type',
        'permissions',
        'device_token',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expire_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /**
     * Check if user is Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    /**
     * Check if user is In-House Admin
     */
    public function isInHouseAdmin(): bool
    {
        return $this->user_type === 'inhouse_admin';
    }

    /**
     * Check if user has specific module/action permission
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->isInHouseAdmin()) {
            return false;
        }

        if (!is_array($this->permissions)) {
            return false;
        }

        // Direct match
        if (in_array($permission, $this->permissions, true)) {
            return true;
        }

        // Wildcard or module match (e.g. 'clients' or 'clients.*' matches 'clients.view')
        $parts = explode('.', $permission);
        $module = $parts[0];

        if (in_array($module, $this->permissions, true) || in_array("{$module}.*", $this->permissions, true) || in_array('*', $this->permissions, true)) {
            return true;
        }

        return false;
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function inspectionAssigns()
    {
        return $this->hasMany(InspectionAssign::class, 'inspector_id');
    }


    public function inspectionBookings()
    {
        return $this->hasMany(InspectionBooking::class, 'homeowner_id');
    }

    //no need at now
    public function inspectorPayouts()
{
    return $this->hasMany(\App\Models\InspectorPayout::class, 'inspector_id');
}


    public function inspectionPayments()
    {
        return $this->hasMany(\App\Models\InspectionPayment::class, 'inspector_id');
    }

    public function clientBookings()
    {
        return $this->hasMany(InspectionBooking::class, 'client_id');
    }
}
