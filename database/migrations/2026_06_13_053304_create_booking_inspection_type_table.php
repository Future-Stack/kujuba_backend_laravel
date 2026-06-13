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
        Schema::create('booking_inspection_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_booking_id')->constrained('inspection_bookings')->onDelete('cascade');
            $table->foreignId('inspection_type_id')->constrained('inspection_types')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_inspection_type');
    }
};
