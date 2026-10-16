<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercise_videos', function (Blueprint $table) {
            $table->string('media_type', 32)->default('video')->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('exercise_videos', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });
    }
};
