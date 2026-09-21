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
        Schema::create('client_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->string('send_time', 10)->default('09:00')->comment('Send time in HH:mm format');
            $table->string('day_of_week', 10)->nullable()->comment('mon, tue, wed, thu, fri, sat, sun for weekly');
            $table->unsignedTinyInteger('day_of_month')->nullable()->default(1)->comment('1-31 for monthly');
            $table->string('inspection_status', 30)->default('all')->comment('all, completed, in_progress, pending');
            $table->boolean('is_enabled')->default(true);
            $table->string('timezone', 50)->default('America/New_York');
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_report_schedules');
    }
};
