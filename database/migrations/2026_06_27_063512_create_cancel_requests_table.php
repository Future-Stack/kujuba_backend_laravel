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
        Schema::create('cancel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_assign_id')->constrained('inspection_assigns')->onDelete('cascade');
            $table->foreignId('inspection_booking_id')->nullable()->constrained('inspection_bookings')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('problem')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cancel_requests');
    }
};
