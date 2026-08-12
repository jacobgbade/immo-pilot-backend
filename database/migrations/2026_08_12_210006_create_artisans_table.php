<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('trade', ['plombier', 'electricien', 'peintre', 'macon', 'climatisation', 'serrurier']);
            $table->string('phone');
            $table->string('zone')->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('interventions_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artisans');
    }
};
