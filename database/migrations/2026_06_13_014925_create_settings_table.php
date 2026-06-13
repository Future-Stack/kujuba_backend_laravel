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
            $table->string('support_mail');
            $table->integer('max_inspector_area');
            $table->integer('inspector_response_time');
            $table->integer('urgent_booking_lead');
            $table->integer('report_deadline');
            $table->decimal('platform_commission', 5, 2);
            $table->boolean('auto_approve')->default(false);
            $table->decimal('urgent_inspection_fee', 10, 2)->nullable();
            $table->decimal('late_cancellation_penalty', 10, 2)->nullable();
            $table->decimal('last_minute_cancel_penalty', 10, 2)->nullable();
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
