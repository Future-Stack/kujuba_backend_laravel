<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'id'         => 1,
                'img'        => 'four_point.png',
                'title'      => 'Four Point Inspection',
                'short_desc' => 'Comprehensive inspection covering HVAC, electrical, plumbing, and roof systems.',
                'price'      => 399.00,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 2,
                'img'        => 'roof_inspection.png',
                'title' => 'Roof Inspection',
                'short_desc' => 'Detailed inspection of the roof\'s condition, safety, and possible damage.',
                'price'      => 250.00,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 3,
                'img'        => 'flood_elevation.png',
                'title'      => 'Flood Elevation',
                'short_desc' => 'Inspection to assess flood risk and verify the property\'s elevation level.',
                'price'      => 270.00,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 4,
                'img'        => 'wind_mitigation.png',
                'title'      => 'Wind Mitigation',
                'short_desc' => 'Inspection to evaluate your home\'s resistance against strong wind and storms.',
                'price'      => 259.00,
                'status'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('inspection_types')->insert($types);
    }
}