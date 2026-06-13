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
        Schema::create('inspection_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_booking_id')->constrained('inspection_bookings')->onDelete('cascade');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('trx_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('urgentStatus')->nullable();
            $table->string('stripe_id')->nullable();
            $table->boolean('is_disbursed')->default(false);
            $table->decimal('penalty_amount', 10, 2)->nullable();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_payments');
    }
};
