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
        Schema::create('inspection_assigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_booking_id')->constrained('inspection_bookings')->onDelete('cascade');
            $table->foreignId('inspector_id')->constrained('users')->onDelete('cascade');
            $table->decimal('distance', 10, 2)->nullable();
            $table->string('estimate_time')->nullable();
            $table->boolean('isAssignedAdmin')->default(false);
            $table->boolean('isReschedule')->default(false);
            $table->string('status')->default('assigned')->comment('assigned,started,rescheduled,reports,cancelled,completed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_assigns');
    }
};
