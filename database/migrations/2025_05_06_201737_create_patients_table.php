
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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();


            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->date('date_of_birth');
            $table->string('address');
            $table->string('phone_number');
            $table->enum('gender',['male','female']);
            $table->string('blood_type')->nullable();
            $table->text('chronic_conditions')->nullable(); // JSON array of conditions
            $table->string('insurance_provider')->nullable();
            $table->string('emergency_contact');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};

