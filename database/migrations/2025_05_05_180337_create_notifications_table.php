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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('type');
            $table->text('message');
            $table->datetime('read_at')->nullable();

            $table->morphs('notifiable');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
