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
        Schema::create('decline_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspector_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('inspection_booking_id')->constrained('inspection_bookings')->onDelete('cascade');
            $table->string('status')->default('declined');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decline_inspections');
    }
};
