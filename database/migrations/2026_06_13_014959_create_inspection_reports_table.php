<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_reports', function (Blueprint $table) {

            $table->id();

            // relation
            $table->foreignId('inspection_assign_id')
                ->constrained('inspection_assigns')
                ->onDelete('cascade');

            // inspector notes
            $table->text('notes')->nullable();

            // photos + videos (json)
            $table->json('media')->nullable();

            //  final report file (pdf/jpg)
            $table->string('report_file')->nullable();

            //  favorite system
            $table->boolean('is_favorite')->default(false);

            //  workflow status
            $table->enum('status', [
                'pending',
                'started',
                'completed',
                'cancelled',
                'archived'
            ])->default('pending');

            // ⏱ workflow timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reports');
    }
};