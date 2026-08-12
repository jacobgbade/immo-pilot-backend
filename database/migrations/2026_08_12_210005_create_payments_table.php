<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            // The rent period this payment covers, e.g. "2026-08" — distinct from paid_at,
            // since a tenant might pay late for a period that started weeks earlier.
            $table->string('period', 7);
            $table->date('paid_at');
            $table->enum('method', ['mobile_money', 'especes', 'bancaire'])->default('mobile_money');
            $table->string('reference')->unique();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            // One payment per lease per period — prevents double-recording the same month.
            $table->unique(['lease_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
