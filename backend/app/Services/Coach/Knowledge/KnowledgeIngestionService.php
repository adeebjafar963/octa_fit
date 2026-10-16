<?php

namespace App\Services\Coach\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11F — ingest sources/documents/chunks idempotently.
 */
class KnowledgeIngestionService
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
    ) {}

    /**
     * @param  array<string, mixed>  $source
     * @param  list<array{title: string, topic: string, document_identifier: string, language?: string, body: string, metadata?: array}>  $documents
     */
    public function upsertSourceWithDocuments(array $source, array $documents): KnowledgeSource
    {
        $model = KnowledgeSource::query()->updateOrCreate(
            [
                'name' => $source['name'],
                'organization' => $source['organization'] ?? null,
            ],
            [
                'source_type' => $source['source_type'],
                'evidence_level' => $source['evidence_level'],
                'url' => $source['url'] ?? null,
                'version' => $source['version'] ?? null,
                'publication_year' => $source['publication_year'] ?? null,
                'publication_date' => $source['publication_date'] ?? null,
                'reliability_level' => $source['reliability_level'] ?? 'high',
                'language' => $source['language'] ?? 'en',
                'is_active' => $source['is_active'] ?? true,
                'metadata' => $source['metadata'] ?? [],
            ],
        );

        foreach ($documents as $doc) {
            $this->upsertDocument($model, $doc);
        }

        return $model->fresh(['documents.chunks']);
    }

    /**
     * @param  array{title: string, topic: string, document_identifier: string, language?: string, body: string, metadata?: array}  $doc
     */
    public function upsertDocument(KnowledgeSource $source, array $doc): KnowledgeDocument
    {
        $body = trim((string) $doc['body']);
        $hash = $this->chunker->hash($body);

        $document = KnowledgeDocument::query()->updateOrCreate(
            [
                'knowledge_source_id' => $source->id,
                'document_identifier' => $doc['document_identifier'],
            ],
            [
                'title' => $doc['title'],
                'topic' => $doc['topic'],
                'language' => $doc['language'] ?? $source->language ?? 'en',
                'content_hash' => $hash,
                'is_active' => $doc['is_active'] ?? true,
                'metadata' => $doc['metadata'] ?? [],
            ],
        );

        // Rebuild chunks only when content changes (idempotent).
        $existingHash = $document->getOriginal('content_hash') ?? $document->content_hash;
        $needsRebuild = KnowledgeChunk::query()->where('knowledge_document_id', $document->id)->doesntExist()
            || $document->wasChanged('content_hash')
            || $existingHash !== $hash;

        // Always sync chunks when body hash differs from stored chunk set signature.
        $chunkPayload = $this->chunker->chunk($body);
        $currentSig = hash('sha256', collect($chunkPayload)->pluck('content_hash')->implode('|'));
        $storedSig = KnowledgeChunk::query()
            ->where('knowledge_document_id', $document->id)
            ->orderBy('chunk_index')
            ->pluck('content_hash')
            ->implode('|');
        $storedSig = $storedSig === '' ? '' : hash('sha256', $storedSig);

        if ($needsRebuild || $currentSig !== $storedSig) {
            DB::transaction(function () use ($document, $chunkPayload, $doc) {
                KnowledgeChunk::query()->where('knowledge_document_id', $document->id)->delete();
                foreach ($chunkPayload as $chunk) {
                    KnowledgeChunk::query()->create([
                        'knowledge_document_id' => $document->id,
                        'chunk_index' => $chunk['chunk_index'],
                        'content' => $chunk['content'],
                        'topic' => $doc['topic'],
                        'token_estimate' => $chunk['token_estimate'],
                        'content_hash' => $chunk['content_hash'],
                        'is_active' => true,
                        'metadata' => [
                            'keywords' => $doc['metadata']['keywords'] ?? [],
                            'embedding_ready' => true,
                        ],
                    ]);
                }
            });
        }

        return $document->fresh('chunks');
    }
}
