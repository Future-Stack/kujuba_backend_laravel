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
            if (!Schema::hasColumn('profiles', 'company_name')) {
                $table->string('company_name')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('profiles', 'client_type')) {
                $table->string('client_type')->nullable()->after('company_name')->comment('insurance_company, realtor, broker, agency, etc.');
            }
        });

        Schema::table('inspection_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('inspection_bookings', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('homeowner_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('inspection_bookings', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            }
        });

        Schema::table('profiles', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('profiles', 'company_name')) {
                $columnsToDrop[] = 'company_name';
            }
            if (Schema::hasColumn('profiles', 'client_type')) {
                $columnsToDrop[] = 'client_type';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
