<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InspectorPayout;

class InspectorPayoutSeeder extends Seeder
{
    public function run(): void
    {
        InspectorPayout::updateOrCreate(
            [
                'inspection_assign_id' => 1,
                'inspection_payment_id' => 1,
            ],
            [
                'inspector_id' => 3,
                'amount' => 649.00,
                'platform_fee' => 20.00,
                'currency' => 'USD',
                'status' => 'paid',
                'payment_type' => 'disbursement',
                'method' => 'stripe',
                'transaction_id' => 'test_txn_123',
                'stripe_transfer_id' => 'tr_test_123',
                'is_disbursed' => 1,
                'calculated_at' => now(),
                'paid_at' => now(),
                'note' => 'Seeder test payout',
            ]
        );
    }
}