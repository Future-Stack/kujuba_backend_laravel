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
        Schema::create('reschedule_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_assign_id')->nullable()->constrained('inspection_assigns')->onDelete('cascade');
            $table->foreignId('inspection_booking_id')->nullable()->constrained('inspection_bookings')->onDelete('cascade');
            $table->foreignId('accepted_inspector_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->time('time');
            $table->string('shift')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reschedule_inspections');
    }
};
