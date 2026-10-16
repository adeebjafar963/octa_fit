<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64); // usda_fdc_fixture | usda_fdc
            $table->string('external_id', 64);
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('brand')->nullable();
            $table->string('category', 64)->nullable(); // grain, protein, dairy, fruit, vegetable, other
            $table->string('preparation', 32)->default('unknown'); // raw, cooked, boiled, baked, grilled, fried, steamed, hard_boiled, roasted, unknown
            $table->string('description_en')->nullable();
            $table->string('description_ar')->nullable();
            // Nutrients stored per 100 g of the described food form (authoritative for basis=per_100g).
            $table->decimal('calories_kcal_per_100g', 8, 2);
            $table->decimal('protein_g_per_100g', 8, 2);
            $table->decimal('carbohydrates_g_per_100g', 8, 2);
            $table->decimal('fat_g_per_100g', 8, 2);
            $table->decimal('fiber_g_per_100g', 8, 2)->nullable();
            $table->decimal('sugar_g_per_100g', 8, 2)->nullable();
            $table->decimal('sodium_mg_per_100g', 8, 2)->nullable();
            // Optional verified piece serving (grams). Null = count-based calc not allowed.
            $table->decimal('piece_grams', 8, 2)->nullable();
            $table->string('piece_label_en')->nullable(); // e.g. "1 large egg"
            $table->string('piece_label_ar')->nullable();
            $table->boolean('is_generic')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('source_retrieved_at')->nullable();
            $table->string('source_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['is_active', 'name_en']);
            $table->index(['category', 'preparation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
