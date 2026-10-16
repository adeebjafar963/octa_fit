<?php

namespace App\Services\Admin;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use Illuminate\Support\Collection;

/**
 * Phase 11F — read-only knowledge admin foundation.
 */
class AdminKnowledgeService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listSources(): Collection
    {
        return KnowledgeSource::query()
            ->withCount('documents')
            ->orderBy('organization')
            ->orderBy('name')
            ->get()
            ->map(fn (KnowledgeSource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'organization' => $s->organization,
                'source_type' => $s->source_type,
                'evidence_level' => $s->evidence_level,
                'url' => $s->url,
                'publication_year' => $s->publication_year,
                'reliability_level' => $s->reliability_level,
                'language' => $s->language,
                'is_active' => (bool) $s->is_active,
                'documents_count' => $s->documents_count,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listDocuments(?int $sourceId = null): Collection
    {
        $q = KnowledgeDocument::query()->with('source')->withCount('chunks')->orderBy('topic')->orderBy('title');
        if ($sourceId !== null) {
            $q->where('knowledge_source_id', $sourceId);
        }

        return $q->get()->map(fn (KnowledgeDocument $d) => [
            'id' => $d->id,
            'knowledge_source_id' => $d->knowledge_source_id,
            'source_name' => $d->source?->name,
            'title' => $d->title,
            'topic' => $d->topic,
            'language' => $d->language,
            'document_identifier' => $d->document_identifier,
            'content_hash' => $d->content_hash,
            'is_active' => (bool) $d->is_active,
            'chunks_count' => $d->chunks_count,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listChunks(?int $documentId = null): Collection
    {
        $q = KnowledgeChunk::query()->with('document.source')->orderBy('knowledge_document_id')->orderBy('chunk_index');
        if ($documentId !== null) {
            $q->where('knowledge_document_id', $documentId);
        }

        return $q->limit(200)->get()->map(fn (KnowledgeChunk $c) => [
            'id' => $c->id,
            'knowledge_document_id' => $c->knowledge_document_id,
            'document_title' => $c->document?->title,
            'source_name' => $c->document?->source?->name,
            'chunk_index' => $c->chunk_index,
            'topic' => $c->topic,
            'token_estimate' => $c->token_estimate,
            'content_hash' => $c->content_hash,
            'is_active' => (bool) $c->is_active,
            'content_preview' => mb_substr($c->content, 0, 160),
        ]);
    }
}
