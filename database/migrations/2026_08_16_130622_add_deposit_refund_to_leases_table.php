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
        Schema::table('leases', function (Blueprint $table) {
            // Art. 69-70 loi n°2022-30: le solde restitué au locataire en fin de bail,
            // après déduction des dommages constatés et des impayés éventuels.
            $table->unsignedBigInteger('deposit_refund_amount')->nullable();
            $table->text('deposit_refund_notes')->nullable();
            $table->date('deposit_refunded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn(['deposit_refund_amount', 'deposit_refund_notes', 'deposit_refunded_at']);
        });
    }
};
