<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content', 1000);
            $table->string('image_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
