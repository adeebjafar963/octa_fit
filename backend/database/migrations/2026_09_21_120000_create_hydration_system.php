<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedInteger('water_goal_ml')->nullable()->after('weight_kg');
            $table->boolean('water_goal_is_custom')->default(false)->after('water_goal_ml');
        });

        Schema::create('water_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_ml');
            $table->timestamp('consumed_at');
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_intakes');

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['water_goal_ml', 'water_goal_is_custom']);
        });
    }
};
