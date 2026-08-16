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
        Schema::create('demand_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            // "YYYY-MM" unpaid period this mise en demeure covers.
            $table->string('period', 7);
            $table->unsignedBigInteger('amount');
            // Art. 75-76 loi n°2022-30: starts the locataire's legal 1-month payment
            // window before the bailleur may seek expulsion via the tribunal.
            $table->date('sent_at');
            $table->date('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_letters');
    }
};
