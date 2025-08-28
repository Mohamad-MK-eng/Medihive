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
      Schema::create('prescriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('report_id')->constrained('reports')->onDelete('cascade');
<<<<<<< HEAD
    $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
    $table->string('medication');
    $table->string('dosage');
    $table->string('frequency')->nullable(); // e.g., "3x/day"
    $table->text('instructions')->nullable(); // e.g., "After meal"
=======
   //check this relation
    $table->foreignId('appointment_id')->constrained('appointments')->onDelete('cascade');
    $table->string('medication');
    $table->string('dosage');
    $table->string('frequency')->nullable();
    $table->text('instructions')->nullable();
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
    $table->boolean('is_completed')->default(false);
    $table->date('issue_date')->nullable();
    $table->date('expiry_date')->nullable();
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
