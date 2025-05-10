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
        Schema::create('salary_settings', function (Blueprint $table) {
            $table->id();

            $table->enum('payment_type', ['hourly', 'monthly', 'performance_based']);
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('per_patient_bonus', 10, 2)->nullable();
            $table->decimal('per_appointment_bonus', 10, 2)->nullable();
            $table->json('performance_thresholds')->nullable(); // Performance-based targets
            $table->timestamps();        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_settings');
    }
};
