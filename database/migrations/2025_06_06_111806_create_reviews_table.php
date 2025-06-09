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
    $table->foreignId('patient_id')->constrained();
    $table->foreignId('doctor_id')->constrained();
    $table->foreignId('appointment_id')->constrained();
    $table->tinyInteger('rating')->unsigned()->between(1, 5);
    $table->text('comment')->nullable();
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
