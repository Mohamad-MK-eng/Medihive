<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
<<<<<<< HEAD
           // in the previos versions i deleted this
    $table->foreignId('secretary_id')->nullable()->constrained('secretaries')->onDelete('set null'); // Make nullable
=======
            $table->foreignId('secretary_id')->nullable()->constrained('secretaries')->onDelete('set null');
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80

            $table->decimal('amount', 10, 2);
            $table->enum('type', ['deposit', 'payment', 'refund', 'withdrawal', 'fee']);
            $table->string('reference');
<<<<<<< HEAD
=======
    Schema::create('wallet_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->decimal('amount', 10, 2);
$table->enum('type', ['deposit', 'payment', 'refund', 'withdrawal','fee']);
   $table->string('reference');
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->text('notes')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->timestamps();
        });
<<<<<<< HEAD
    }
=======
    });
}
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
