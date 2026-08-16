<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // Fixed reference point for the legal 2% rent-revision cap (Art. 68, loi
            // n°2022-30) — the cap applies to the rent at signing, not the rent before
            // the most recent edit, so this must never change once set.
            $table->unsignedBigInteger('initial_rent_amount')->nullable();
        });

        DB::table('leases')->whereNull('initial_rent_amount')->update([
            'initial_rent_amount' => DB::raw('rent_amount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('initial_rent_amount');
        });
    }
};
