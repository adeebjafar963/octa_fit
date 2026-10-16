<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_category_id')->constrained('training_categories')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('difficulty', 32)->default('beginner'); // beginner|intermediate|advanced
            $table->text('instructions_en')->nullable();
            $table->text('instructions_ar')->nullable();
            $table->text('safety_notes_en')->nullable();
            $table->text('safety_notes_ar')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('target')->nullable(); // muscle group / technique type
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['training_category_id', 'is_active']);
            $table->index('difficulty');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
