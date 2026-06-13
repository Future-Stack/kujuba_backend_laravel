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
        Schema::create('inspection_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homeowner_id')->constrained('users')->onDelete('cascade');
            $table->string('property_address');
            $table->string('property_type');
            $table->string('property_size');
            $table->text('note')->nullable();
            $table->string('property_img')->nullable();
            $table->date('booking_date');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->string('scheduled_shift')->nullable();
            $table->boolean('urgent_status')->default(false);
            $table->string('status')->default('pending');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->tinyInteger('isRescheduled')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_bookings');
    }
};
