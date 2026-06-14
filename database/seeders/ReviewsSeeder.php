<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            Review::updateOrCreate(
                [
                    'homeowner_id'        => 2,
                    'inspection_assign_id'=> 1,
                ],
                [
                    'rating'          => 5,
                    'description'     => 'Excellent inspection service. The inspector was thorough and professional.',
                    'status'          => 'active',
                    'suspendInspector'=> 0,
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to seed reviews table: ' . $e->getMessage());
        }
    }
}
