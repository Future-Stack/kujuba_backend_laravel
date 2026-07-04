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
        Schema::create('inspector_payouts', function (Blueprint $table) {
                    $table->id();

                    // Inspector
                    $table->foreignId('inspector_id')
                        ->constrained('users')
                        ->cascadeOnDelete();

                    // Inspection context
                    $table->foreignId('inspection_assign_id')
                        ->nullable()
                        ->constrained('inspection_assigns')
                        ->cascadeOnDelete();

                    $table->foreignId('inspection_payment_id')
                        ->nullable()
                        ->constrained('inspection_payments')
                        ->nullOnDelete();

                    // MONEY (Inspector earning)
                    $table->decimal('amount', 10, 2)->default(0); // inspector earning
                   
                    $table->decimal('platform_fee', 10, 2)->default(0);

                    // Currency
                    $table->string('currency', 10)->default('USD');

                    // Status
                    $table->enum('status', ['pending', 'processing', 'paid', 'failed'])
                        ->default('pending');

                    // Type
                    $table->string('payment_type')
                        ->default('inspection_fee');

                    // Method
                    $table->enum('method', ['stripe', 'bank', 'cash', 'manual'])
                        ->nullable();

                    // Stripe tracking
                    $table->string('transaction_id')->nullable();
                    $table->string('stripe_transfer_id')->nullable();

                    // Important tracking
                    $table->boolean('is_disbursed')->default(false);
                    $table->timestamp('calculated_at')->nullable();
                    $table->timestamp('paid_at')->nullable();

                    $table->text('note')->nullable();

                    $table->timestamps();
                });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspector_payouts');
    }
};
