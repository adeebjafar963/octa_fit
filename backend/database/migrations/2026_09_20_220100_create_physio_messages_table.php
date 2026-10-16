<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physio_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('physio_assessments')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->timestamps();

            $table->index(['assessment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physio_messages');
    }
};
