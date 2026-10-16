<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11H — controlled Coach long-term personalization memory.
 * Not conversation history. Scoped per user. Allergies remain in nutrition_preferences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('key', 128);
            $table->json('value');
            $table->string('source', 32)->default('explicit'); // explicit|inferred
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->boolean('explicit')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category', 'key'], 'coach_mem_user_cat_key_uq');
            $table->index(['user_id', 'active', 'category'], 'coach_mem_user_active_cat_idx');
            $table->index(['user_id', 'expires_at'], 'coach_mem_user_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_memories');
    }
};
