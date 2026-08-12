<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The in-app notification feed (spec section 33). Named "alerts" rather than
     * "notifications" to avoid colliding with Laravel's own notifications table if
     * that's ever published via `notifications:table` for email/push channels later.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['paiements', 'maintenance', 'contrats', 'systeme']);
            $table->string('icon', 8)->default('🔔');
            $table->string('message');
            // Polymorphic-ish pointer so the client can deep-link ("Voir le contrat" etc.)
            // without the API needing a bespoke shape per category.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
