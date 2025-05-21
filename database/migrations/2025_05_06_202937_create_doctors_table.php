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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
$table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
            $table->string('specialty')->nullable(false);
            $table->text('bio')->nullable();
                        $table->json('workdays')->nullable();
$table->string('profile_picture')->nullable();
            $table->float('rating')->default(0);
            $table->decimal('consultation_fee',8,2)->default(100);
            $table->integer('experience_years')->default(2);
            $table->double('salary')->default(7000);



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
