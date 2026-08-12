<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            // "maintenance" is deliberately not a valid value here — those costs are read
            // from maintenance_requests.final_cost/estimated_cost instead of logged twice.
            $table->enum('category', ['electricite', 'eau', 'autres']);
            $table->unsignedBigInteger('amount');
            $table->string('description')->nullable();
            $table->date('spent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
