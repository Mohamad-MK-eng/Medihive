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
   // Create new migration: php artisan make:migration create_prescriptions_table
Schema::create('prescriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
    $table->string('medication');
    $table->string('dosage');
    $table->text('instructions');
    $table->date('issue_date');
    $table->date('expiry_date');
    $table->timestamps();
});
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
