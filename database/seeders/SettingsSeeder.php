<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            Setting::updateOrCreate(
                ['id' => 1], // singleton row
                [
                    'platform_name'             => 'CONNECT TO INSPECT',
                    'support_mail'              => 'support@inspecthub.com',
                    'max_inspector_area'        => 25,
                    'inspector_response_time'   => 30,
                    'urgent_booking_lead'       => 4,
                    'report_deadline'           => 48,
                    'platform_commission'       => 20.00,
                    'auto_approve'              => false,
                    'urgent_inspection_fee'     => 50.00,
                    'late_cancellation_penalty' => 50.00,
                    'last_minute_cancel_penalty'=> 75.00,
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to seed settings table: ' . $e->getMessage());
        }
    }
}
