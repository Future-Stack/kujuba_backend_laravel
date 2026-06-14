<?php

namespace Database\Seeders;

use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create booking
        $booking = InspectionBooking::updateOrCreate(
            ['id' => 1],
            [
                'homeowner_id'    => 2,
                'property_address'=> '123 Main Street, Miami, FL',
                'property_type'   => 'Residential',
                'property_size'   => '2000 sqft',
                'note'            => 'Urgent inspection required',
                'property_img'    => 'property1.png',
                'booking_date'    => now(),
                'scheduled_date'  => now()->addDays(2),
                'scheduled_time'  => '10:00:00',
                'scheduled_shift' => 'morning',
                'urgent_status'   => true,
                'status'          => 'pending',
                'latitude'        => 25.7617,
                'longitude'       => -80.1918,
                'isRescheduled'   => 0,
            ]
        );

        // Attach inspection types (pivot)
        DB::table('booking_inspection_type')->updateOrInsert(
            [
                'inspection_booking_id' => $booking->id,
                'inspection_type_id'    => 1,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        DB::table('booking_inspection_type')->updateOrInsert(
            [
                'inspection_booking_id' => $booking->id,
                'inspection_type_id'    => 2,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // Payment record
        InspectionPayment::updateOrCreate(
            ['inspection_booking_id' => $booking->id],
            [
                'subtotal'              => 649.00,
                'platform_fee'          => 20.00,
                'total'                 => 669.00,
                'trx_id'                => 'TRX-001',
                'status'                => 'pending',
                'urgentStatus'          => 'yes',
                'stripe_id'             => 'STRIPE-001',
                'is_disbursed'          => false,
                'penalty_amount'        => null,
                'refunded_amount'       => null,
            ]
        );

        // Assign inspector
        InspectionAssign::updateOrCreate(
            ['inspection_booking_id' => $booking->id, 'inspector_id' => 3],
            [
                'distance'        => 12.5,
                'estimate_time'   => '30 mins',
                'isAssignedAdmin' => false,
                'isReschedule'    => false,
                'status'          => 'assigned',
            ]
        );
    }

}
