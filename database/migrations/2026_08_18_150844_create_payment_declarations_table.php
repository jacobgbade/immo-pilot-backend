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
        Schema::create('payment_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedBigInteger('amount');
            $table->enum('method', ['mobile_money', 'especes', 'bancaire'])->default('mobile_money');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            // Spec section 0bis: le paiement déclaré par le locataire reste "en attente"
            // jusqu'à confirmation du propriétaire — jamais confirmé automatiquement.
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->timestamp('declared_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_declarations');
    }
};
