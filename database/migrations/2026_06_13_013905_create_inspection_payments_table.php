<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspection_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_booking_id')->constrained('inspection_bookings')->onDelete('cascade');
            $table->foreignId('inspector_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('homeowner_id')->nullable()->constrained('users')->onDelete('cascade');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('urgent_fee', 10, 2)->default(0.00);

            $table->decimal('inspector_share', 10, 2); // inspector’s cut
            $table->decimal('admin_share', 10, 2);     // platform’s cut

            $table->decimal('total', 10, 2);
            $table->string('payment_type')->default('inspection_fee')->comment('inspection_fee', 'refund', 'cancellation_penalty', 'paycut', 'disbursement');
            $table->string('trx_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('urgentStatus')->nullable();
            $table->string('stripe_id')->nullable();
            $table->boolean('is_disbursed')->default(false);
            $table->decimal('penalty_amount', 10, 2)->nullable();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->decimal('paycut_amount', 10, 2)->nullable(); // new field
            $table->enum('payout_status', [
                'pending',
                'processing',
                'paid',
                'failed'
            ])->default('pending');
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
