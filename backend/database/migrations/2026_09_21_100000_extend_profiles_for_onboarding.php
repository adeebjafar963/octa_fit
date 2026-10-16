<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('fitness_level', 32)->nullable()->after('training_goal');
            $table->string('training_experience', 32)->nullable()->after('fitness_level');
            $table->unsignedTinyInteger('training_frequency')->nullable()->after('training_experience');
            $table->unsignedSmallInteger('session_duration_minutes')->nullable()->after('training_frequency');
            $table->string('training_location', 32)->nullable()->after('session_duration_minutes');
            $table->json('equipment')->nullable()->after('training_location');
            $table->json('secondary_goals')->nullable()->after('equipment');
            $table->string('activity_level', 32)->nullable()->after('secondary_goals');
            $table->string('dietary_preference', 64)->nullable()->after('activity_level');
            $table->unsignedTinyInteger('meals_per_day')->nullable()->after('dietary_preference');
            $table->decimal('sleep_hours', 3, 1)->nullable()->after('meals_per_day');
            $table->string('injury_status', 32)->nullable()->after('sleep_hours');
            $table->json('injury_areas')->nullable()->after('injury_status');
            $table->text('injury_description')->nullable()->after('injury_areas');
            $table->boolean('professional_evaluation')->nullable()->after('injury_description');
            $table->unsignedTinyInteger('onboarding_step')->default(0)->after('professional_evaluation');
            $table->timestamp('profile_completed_at')->nullable()->after('onboarding_step');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'fitness_level',
                'training_experience',
                'training_frequency',
                'session_duration_minutes',
                'training_location',
                'equipment',
                'secondary_goals',
                'activity_level',
                'dietary_preference',
                'meals_per_day',
                'sleep_hours',
                'injury_status',
                'injury_areas',
                'injury_description',
                'professional_evaluation',
                'onboarding_step',
                'profile_completed_at',
            ]);
        });
    }
};
