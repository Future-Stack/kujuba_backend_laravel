<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InspectionType;

class InspectionTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'id' => 1,
                'img' => 'inspection_types/four_point.png',
                'title' => 'Four Point Inspection',
                'short_desc' => 'Comprehensive inspection covering HVAC, electrical, plumbing, and roof systems.',
                'price' => 399.00,
                'status' => 1,
            ],
            [
                'id' => 2,
                'img' => 'inspection_types/roof_inspection.png',
                'title' => 'Roof Inspection',
                'short_desc' => "Detailed inspection of the roof's condition, safety, and possible damage.",
                'price' => 250.00,
                'status' => 1,
            ],
            [
                'id' => 3,
                'img' => null,
                'title' => 'Flood Elevation',
                'short_desc' => "Inspection to assess flood risk and verify the property's elevation level.",
                'price' => 270.00,
                'status' => 1,
            ],
            [
                'id' => 4,
                'img' => null,
                'title' => 'Wind Mitigation',
                'short_desc' => "Inspection to evaluate your home's resistance against strong wind and storms.",
                'price' => 259.00,
                'status' => 1,
            ]
        ];

        foreach ($types as $type) {
            InspectionType::updateOrCreate(
                ['id' => $type['id']],
                [
                    'img' => $type['img'] ?? null,
                    'title' => $type['title'],
                    'short_desc' => $type['short_desc'],
                    'price' => $type['price'],
                    'status' => $type['status'],
                ]
            );
        }
    }
}