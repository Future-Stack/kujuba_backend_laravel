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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name');
            $table->string('support_mail')->nullable();
            $table->integer('max_inspector_area')->default(0);
            $table->integer('inspector_response_time')->default(30)->comment('minutes');
            $table->integer('urgent_booking_lead')->default(4)->comment('hours');
            $table->integer('report_deadline')->default(48)->comment('hours');
            $table->decimal('platform_commission', 5, 2)->default(20)->comment('percent');
            $table->boolean('auto_approve')->default(false);
            $table->decimal('urgent_inspection_fee', 10, 2)->default(50);
            $table->decimal('late_cancellation_penalty', 10, 2)->default(50);
            $table->decimal('last_minute_cancel_penalty', 10, 2)->default(75);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
