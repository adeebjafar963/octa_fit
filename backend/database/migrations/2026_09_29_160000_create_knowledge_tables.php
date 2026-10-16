<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('source_type', 64); // position_stand, systematic_review, meta_analysis, official_guidance, review, educational
            $table->string('evidence_level', 64); // systematic_review, meta_analysis, position_stand, randomized_trial, official_guidance, review, educational
            $table->string('url', 512)->nullable();
            $table->string('version', 64)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->date('publication_date')->nullable();
            $table->string('reliability_level', 32)->default('high'); // high, medium, educational
            $table->string('language', 8)->default('en');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // population, limitations, applicability, study_type, topics[]
            $table->timestamps();

            $table->index(['is_active', 'evidence_level']);
            $table->index(['organization']);
        });

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->string('title');
            $table->string('topic', 64); // training, nutrition, recovery, combat, home, hydration, sleep
            $table->string('language', 8)->default('en');
            $table->string('document_identifier', 128); // stable slug within source
            $table->string('content_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['knowledge_source_id', 'document_identifier'], 'kd_source_doc_uid');
            $table->index(['topic', 'is_active'], 'kd_topic_active_idx');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->string('topic', 64);
            $table->unsignedInteger('token_estimate')->default(0);
            $table->string('content_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // keywords[], embedding_ready
            $table->timestamps();

            $table->unique(['knowledge_document_id', 'chunk_index'], 'kc_doc_chunk_uid');
            $table->index(['topic', 'is_active'], 'kc_topic_active_idx');
            $table->index(['content_hash'], 'kc_content_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_sources');
    }
};
