<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_categories', function (Blueprint $table) {
            $table->string('level', 32)->default('beginner')->after('type');
            $table->index(['type', 'level', 'is_active']);
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->string('equipment', 64)->nullable()->after('target');
            $table->json('primary_muscles')->nullable()->after('equipment');
            $table->json('secondary_muscles')->nullable()->after('primary_muscles');
            $table->string('metric_type', 32)->default('reps')->after('secondary_muscles');
            $table->unsignedTinyInteger('sets')->nullable()->after('metric_type');
            $table->unsignedSmallInteger('reps_min')->nullable()->after('sets');
            $table->unsignedSmallInteger('reps_max')->nullable()->after('reps_min');
            $table->unsignedSmallInteger('duration_seconds')->nullable()->after('reps_max');
            $table->unsignedSmallInteger('rest_seconds')->nullable()->after('duration_seconds');
            $table->unsignedTinyInteger('rounds')->nullable()->after('rest_seconds');
            $table->unsignedSmallInteger('round_seconds')->nullable()->after('rounds');
            $table->unsignedSmallInteger('round_rest_seconds')->nullable()->after('round_seconds');
        });

        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('slug')->unique();
            $table->string('type', 32);
            $table->string('level', 32);
            $table->foreignId('training_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['type', 'level', 'is_active']);
        });

        Schema::create('training_program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('target_sets')->nullable();
            $table->unsignedSmallInteger('target_reps_min')->nullable();
            $table->unsignedSmallInteger('target_reps_max')->nullable();
            $table->unsignedSmallInteger('target_duration_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->unsignedTinyInteger('rounds')->nullable();
            $table->unsignedSmallInteger('round_seconds')->nullable();
            $table->unsignedSmallInteger('round_rest_seconds')->nullable();
            $table->timestamps();
            $table->index(['training_program_id', 'sort_order']);
        });

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->nullable();
            $table->string('level', 32)->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_total_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('exercises_completed')->default(0);
            $table->unsignedInteger('sets_completed')->default(0);
            $table->unsignedInteger('total_reps')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('workout_session_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_exercise_id')->nullable()->constrained('training_program_exercises')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('target_sets')->nullable();
            $table->unsignedSmallInteger('target_reps_min')->nullable();
            $table->unsignedSmallInteger('target_reps_max')->nullable();
            $table->unsignedSmallInteger('target_duration_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->unsignedTinyInteger('rounds')->nullable();
            $table->unsignedSmallInteger('round_seconds')->nullable();
            $table->unsignedSmallInteger('round_rest_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['workout_session_id', 'sort_order']);
        });

        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedSmallInteger('reps')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->boolean('is_warmup')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['workout_session_exercise_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workout_session_exercises');
        Schema::dropIfExists('workout_sessions');
        Schema::dropIfExists('training_program_exercises');
        Schema::dropIfExists('training_programs');

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn([
                'equipment',
                'primary_muscles',
                'secondary_muscles',
                'metric_type',
                'sets',
                'reps_min',
                'reps_max',
                'duration_seconds',
                'rest_seconds',
                'rounds',
                'round_seconds',
                'round_rest_seconds',
            ]);
        });

        Schema::table('training_categories', function (Blueprint $table) {
            $table->dropIndex(['type', 'level', 'is_active']);
            $table->dropColumn('level');
        });
    }
};
