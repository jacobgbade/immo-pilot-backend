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
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->enum('category', [
                'murs', 'plafonds', 'sols', 'portes', 'fenetres', 'plomberie',
                'electricite', 'sanitaires', 'cuisine', 'equipements', 'compteurs',
            ]);
            $table->enum('condition', ['bon', 'moyen', 'mauvais']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
