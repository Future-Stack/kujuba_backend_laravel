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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->decimal('rating');
            $table->foreignId('homeowner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('inspection_assign_id')->constrained('inspection_assigns')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->comment('active,inactive,flagged');
            $table->tinyInteger('suspendInspector')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
