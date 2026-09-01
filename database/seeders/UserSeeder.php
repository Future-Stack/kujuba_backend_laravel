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
        $admin = User::updateOrCreate(

            ['email' => 'David@connecttoinspect.com'],
            [
                'first_name' => 'David',
                'last_name'  => 'Admin',
                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'admin',
                'email_verified_at' => now(),
            ]
        );

        Profile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'phone'   => '1111111111',
                'address' => 'Florida, USA',
            ]
        );

        // Homeowner
        $homeowner = User::updateOrCreate(
            ['email' => 'homeowner@kujuba.com'],
            [
                'first_name' => 'John',
                'last_name'  => 'Homeowner',
                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'homeowner',
                'email_verified_at' => now(),
            ]
        );

        Profile::updateOrCreate(
            ['user_id' => $homeowner->id],
            [
                'phone'   => '2222222222',
                'address' => 'Miami, Florida',
            ]
        );

        // Inspector
        $inspector = User::updateOrCreate(
            ['email' => 'inspector@kujuba.com'],
            [
                'first_name' => 'Mike',
                'last_name'  => 'Inspector',
                'password'   => Hash::make('Password@123'),
                'status'     => 'active',
                'user_type'  => 'inspector',
                'email_verified_at' => now(),
            ]
        );

        $profile = Profile::updateOrCreate(
            ['user_id' => $inspector->id],
            [
                'phone'   => '3333333333',
                'address' => 'Orlando, Florida',
                'license_number' => 'LIC-12345',
                'license_expiry' => now()->addYear(),
                'insurance_expiry' => now()->addYear(),
                'stripe_onboarding_completed' => 1,
                'stripe_account_id' =>'acct_1TnXRbQK0udB5Dtn'
            ]
        );

        // Inspector Inspection Types
        $profile->inspectionTypes()->syncWithoutDetaching([1, 2]);
    }
}
