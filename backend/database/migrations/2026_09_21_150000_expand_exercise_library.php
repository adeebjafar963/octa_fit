<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_categories', function (Blueprint $table) {
            $table->string('subcategory', 64)->nullable()->after('level');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name_ar');
            $table->string('exercise_type', 32)->default('sets_reps')->after('metric_type');
            $table->string('movement_pattern', 64)->nullable()->after('exercise_type');
            $table->boolean('bodyweight_supported')->default(false)->after('equipment');
            $table->boolean('home_friendly')->default(false)->after('bodyweight_supported');
            $table->boolean('gym_friendly')->default(true)->after('home_friendly');
            $table->boolean('mma_specific')->default(false)->after('gym_friendly');
            $table->json('equipment_alternatives')->nullable()->after('mma_specific');
            $table->unsignedTinyInteger('recommended_rir')->nullable()->after('round_rest_seconds');
            $table->string('recommended_intensity', 64)->nullable()->after('recommended_rir');
            $table->string('tempo')->nullable()->after('recommended_intensity');
            $table->text('common_mistakes_en')->nullable()->after('safety_notes_ar');
            $table->text('common_mistakes_ar')->nullable()->after('common_mistakes_en');
            $table->text('contraindication_notes')->nullable()->after('common_mistakes_ar');
            $table->unsignedBigInteger('progression_exercise_id')->nullable()->after('contraindication_notes');
            $table->unsignedBigInteger('regression_exercise_id')->nullable()->after('progression_exercise_id');
            $table->string('evidence_level', 32)->nullable()->after('regression_exercise_id');
            $table->text('evidence_notes')->nullable()->after('evidence_level');
            $table->string('source_title')->nullable()->after('evidence_notes');
            $table->unsignedSmallInteger('source_year')->nullable()->after('source_title');
            $table->string('source_url', 500)->nullable()->after('source_year');
            $table->timestamp('last_reviewed_at')->nullable()->after('source_url');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->foreign('progression_exercise_id')->references('id')->on('exercises')->nullOnDelete();
            $table->foreign('regression_exercise_id')->references('id')->on('exercises')->nullOnDelete();
        });

        Schema::create('exercise_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alternative_exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['exercise_id', 'alternative_exercise_id'], 'exercise_alt_unique');
        });

        Schema::create('exercise_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_favorites');
        Schema::dropIfExists('exercise_alternatives');

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropForeign(['progression_exercise_id']);
            $table->dropForeign(['regression_exercise_id']);
            $table->dropColumn([
                'slug',
                'exercise_type',
                'movement_pattern',
                'bodyweight_supported',
                'home_friendly',
                'gym_friendly',
                'mma_specific',
                'equipment_alternatives',
                'recommended_rir',
                'recommended_intensity',
                'tempo',
                'common_mistakes_en',
                'common_mistakes_ar',
                'contraindication_notes',
                'progression_exercise_id',
                'regression_exercise_id',
                'evidence_level',
                'evidence_notes',
                'source_title',
                'source_year',
                'source_url',
                'last_reviewed_at',
            ]);
        });

        Schema::table('training_categories', function (Blueprint $table) {
            $table->dropColumn('subcategory');
        });
    }
};
