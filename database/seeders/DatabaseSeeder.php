<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            InspectionTypeSeeder::class,
            UserSeeder::class,
            PagesSeeder::class,
            InspectionsSeeder::class,
            ReviewsSeeder::class,
            FaqSeeder::class,
        ]);
    }
}
