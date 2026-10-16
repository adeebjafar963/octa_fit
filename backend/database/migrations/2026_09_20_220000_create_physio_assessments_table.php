<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physio_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body_area', 80);
            $table->text('symptoms');
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->string('duration', 200)->nullable();
            $table->string('onset', 200)->nullable();
            $table->string('trigger', 500)->nullable();
            $table->string('activity', 200)->nullable();
            $table->text('additional_information')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->boolean('requires_urgent_care')->default(false);
            $table->json('result_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physio_assessments');
    }
};
