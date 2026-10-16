<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->json('allergen_tags')->nullable()->after('source_note');
        });

        Schema::create('nutrition_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('allergies')->nullable(); // hard exclusions
            $table->json('intolerances')->nullable();
            $table->json('disliked')->nullable(); // soft exclusions
            $table->json('liked')->nullable();
            $table->json('excluded_foods')->nullable(); // free-text / food name tokens
            $table->string('dietary_pattern', 64)->nullable(); // omnivore, vegetarian, …
            $table->unsignedTinyInteger('meals_per_day')->nullable();
            $table->string('cost_preference', 32)->nullable(); // deferred optimization note only
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('plan_uuid');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 32)->default('active'); // active, superseded, draft
            $table->string('goal', 64);
            $table->unsignedInteger('calorie_target')->nullable();
            $table->unsignedTinyInteger('meals_per_day')->nullable();
            $table->unsignedTinyInteger('duration_days')->nullable();
            $table->json('payload'); // full 11e.1 plan
            $table->json('assumptions')->nullable();
            $table->timestamps();

            $table->unique(['plan_uuid', 'version']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'plan_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_plans');
        Schema::dropIfExists('nutrition_preferences');

        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('allergen_tags');
        });
    }
};
