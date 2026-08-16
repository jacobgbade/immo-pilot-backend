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
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            // Spec section 11 / Art. 11 loi n°2022-30: état des lieux d'entrée et de sortie —
            // one of each per lease, contradictoirement établi entre bailleur et locataire.
            $table->enum('type', ['entree', 'sortie']);
            $table->enum('form', ['sous_seing_prive', 'huissier'])->default('sous_seing_prive');
            $table->text('notes')->nullable();
            $table->date('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
