<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@kujuba.com',
            'password'   => Hash::make('Password@123'),
            'status'     => 'active',
            'user_type'  => 'admin',
            'email_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $admin->id,
            'phone'   => '1111111111',
            'address' => 'Florida, USA',
        ]);

        // Homeowner
        $homeowner = User::create([
            'first_name' => 'John',
            'last_name'  => 'Homeowner',
            'email'      => 'homeowner@kujuba.com',
            'password'   => Hash::make('Password@123'),
            'status'     => 'active',
            'user_type'  => 'homeowner',
            'email_verified_at' => now(),
        ]);

        Profile::create([
            'user_id' => $homeowner->id,
            'phone'   => '2222222222',
            'address' => 'Miami, Florida',
        ]);

        // Inspector
        $inspector = User::create([
            'first_name' => 'Mike',
            'last_name'  => 'Inspector',
            'email'      => 'inspector@kujuba.com',
            'password'   => Hash::make('Password@123'),
            'status'     => 'active',
            'user_type'  => 'inspector',
            'email_verified_at' => now(),
        ]);

        $profile = Profile::create([
            'user_id' => $inspector->id,
            'phone'   => '3333333333',
            'address' => 'Orlando, Florida',
            'license_number' => 'LIC-12345',
            'license_expiry' => now()->addYear(),
            'insurance_expiry' => now()->addYear(),
        ]);

        // Inspector Inspection Types
        $profile->inspectionTypes()->attach([1, 2]);
    }
}