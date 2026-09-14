<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('profiles', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('address');
            }
            if (!Schema::hasColumn('profiles', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('zip_code');
            }
            if (!Schema::hasColumn('profiles', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('profiles', 'service_radius')) {
                $table->decimal('service_radius', 8, 2)->default(50.00)->after('longitude')->comment('Service radius in miles (default 50 miles)');
            }
        });

        Schema::table('inspection_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('inspection_bookings', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('property_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'service_radius')) {
                $table->dropColumn('service_radius');
            }
            if (Schema::hasColumn('profiles', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('profiles', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('profiles', 'zip_code')) {
                $table->dropColumn('zip_code');
            }
        });

        Schema::table('inspection_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('inspection_bookings', 'zip_code')) {
                $table->dropColumn('zip_code');
            }
        });
    }
};
