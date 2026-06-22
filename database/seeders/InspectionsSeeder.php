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
                'status'          => 'active',
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
                'inspector_share'       => 649.00,
                'admin_share'           => 20.00,
                'total'                 => 669.00,
                'trx_id'                => 'TRX-001',
                'status'                => 'paid',
                'urgentStatus'          => 'yes',
                'stripe_id'             => 'STRIPE-001',
                'is_disbursed'          => true,
                'penalty_amount'        => null,
                'refunded_amount'       => null,
            ]
        );

        // Booking #2
        $booking2 = InspectionBooking::updateOrCreate(
            ['id' => 2],
            [
                'homeowner_id'    => 2,
                'property_address'=> '456 Ocean Drive, Miami, FL',
                'property_type'   => 'Condo',
                'property_size'   => '1200 sqft',
                'note'            => 'Routine inspection',
                'property_img'    => 'property2.png',
                'booking_date'    => now(),
                'scheduled_date'  => now()->addDays(5),
                'scheduled_time'  => '14:00:00',
                'scheduled_shift' => 'afternoon',
                'urgent_status'   => false,
                'status'          => 'pending',
                'latitude'        => 25.7906,
                'longitude'       => -80.1300,
                'isRescheduled'   => 0,
            ]
        );

// Attach inspection types
        DB::table('booking_inspection_type')->updateOrInsert(
            [
                'inspection_booking_id' => $booking2->id,
                'inspection_type_id'    => 1,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        InspectionPayment::updateOrCreate(
            ['inspection_booking_id' => $booking2->id],
            [
                'subtotal'        => 349.00,
                'platform_fee'    => 15.00,
                'inspector_share' => 334.00,
                'admin_share'     => 15.00,
                'total'           => 364.00,
                'trx_id'          => 'TRX-002',
                'status'          => 'paid',
                'urgentStatus'    => 'no',
                'stripe_id'       => 'STRIPE-002',
                'is_disbursed'    => false,
                'penalty_amount'  => null,
                'refunded_amount' => null,
            ]
        );


        // Booking #3
        $booking3 = InspectionBooking::updateOrCreate(
            ['id' => 3],
            [
                'homeowner_id'    => 2,
                'property_address'=> '789 Pine Avenue, Miami, FL',
                'property_type'   => 'Townhouse',
                'property_size'   => '1800 sqft',
                'note'            => 'Follow-up inspection after repair',
                'property_img'    => 'property3.png',
                'booking_date'    => now(),
                'scheduled_date'  => now()->addDays(7),
                'scheduled_time'  => '09:00:00',
                'scheduled_shift' => 'morning',
                'urgent_status'   => true,
                'status'          => 'pending',
                'latitude'        => 25.7750,
                'longitude'       => -80.2100,
                'isRescheduled'   => 1,
            ]
        );

        // Attach inspection types
        DB::table('booking_inspection_type')->updateOrInsert(
            [
                'inspection_booking_id' => $booking3->id,
                'inspection_type_id'    => 2,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        InspectionPayment::updateOrCreate(
            ['inspection_booking_id' => $booking3->id],
            [
                'subtotal'        => 499.00,
                'platform_fee'    => 25.00,
                'inspector_share' => 474.00,
                'admin_share'     => 25.00,
                'total'           => 524.00,
                'trx_id'          => 'TRX-003',
                'status'          => 'paid',
                'urgentStatus'    => 'yes',
                'stripe_id'       => 'STRIPE-003',
                'is_disbursed'    => false,
                'penalty_amount'  => null,
                'refunded_amount' => null,
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
