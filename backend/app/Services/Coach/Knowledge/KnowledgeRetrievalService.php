<?php

namespace App\Services\Coach\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

/**
 * Phase 11F — hybrid lexical/topic knowledge retrieval.
 *
 * Abstraction boundary: ranking lives here so a future vector backend can plug in
 * without changing CoachService or the 11f.1 contract.
 *
 * No external embedding API is required. Semantic search can be added later via
 * optional env-configured providers that fail gracefully.
 */
class KnowledgeRetrievalService
{
    public const SCHEMA_VERSION = '11f.1';

    public function __construct(
        private readonly KnowledgeIntentDetector $intentDetector,
    ) {}

    /**
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    public function retrieveForMessage(string $message, array $userContext = [], int $limit = 5): array
    {
        if (! $this->intentDetector->shouldRetrieve($message)) {
            return $this->emptyContext($message, ['retrieval_not_needed']);
        }

        $analysis = $this->intentDetector->analyze($message, $userContext);

        return $this->retrieve($message, $analysis['topics'], $analysis['keywords'], $userContext, $limit);
    }

    /**
     * @param  list<string>  $topics
     * @param  list<string>  $keywords
     * @param  array<string, mixed>  $userContext
     * @return array<string, mixed>
     */
    public function retrieve(
        string $query,
        array $topics = [],
        array $keywords = [],
        array $userContext = [],
        int $limit = 5,
    ): array {
        $limit = max(1, min(10, $limit));
        $topics = array_values(array_unique(array_filter($topics)));
        $keywords = array_values(array_unique(array_filter($keywords)));
        if ($keywords === []) {
            $keywords = $this->intentDetector->analyze($query, $userContext)['keywords'];
        }
        if ($topics === []) {
            $topics = $this->intentDetector->analyze($query, $userContext)['topics'];
        }

        /** @var Collection<int, KnowledgeChunk> $candidates */
        $candidates = KnowledgeChunk::query()
            ->active()
            ->whereHas('document', function ($q) use ($topics) {
                $q->active()->whereHas('source', fn ($s) => $s->active());
                if ($topics !== []) {
                    $q->where(function ($inner) use ($topics) {
                        $inner->whereIn('topic', $topics);
                        foreach ($topics as $topic) {
                            $inner->orWhere('topic', $topic);
                        }
                    });
                }
            })
            ->with(['document.source'])
            ->limit(200)
            ->get();

        if ($candidates->isEmpty()) {
            // Broaden: any active chunk
            $candidates = KnowledgeChunk::query()
                ->active()
                ->whereHas('document', fn ($q) => $q->active()->whereHas('source', fn ($s) => $s->active()))
                ->with(['document.source'])
                ->limit(200)
                ->get();
        }

        $scored = [];
        foreach ($candidates as $chunk) {
            $score = $this->score($chunk, $query, $topics, $keywords, $userContext);
            if ($score < 12) {
                continue;
            }
            // Require lexical evidence of relevance (avoid topic-only false positives).
            if (! $this->hasLexicalHit($chunk, $query, $keywords)) {
                continue;
            }
            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }

        usort($scored, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['chunk']->id <=> $b['chunk']->id; // deterministic
            }

            return $b['score'] <=> $a['score'];
        });

        $scored = array_slice($scored, 0, $limit);
        $items = [];
        $seenHashes = [];
        foreach ($scored as $row) {
            /** @var KnowledgeChunk $chunk */
            $chunk = $row['chunk'];
            if (isset($seenHashes[$chunk->content_hash])) {
                continue;
            }
            $seenHashes[$chunk->content_hash] = true;
            $items[] = $this->toItem($chunk, (float) $row['score']);
        }

        $limitations = [
            'Initial corpus is intentionally small for the graduation project.',
            'Retrieval is lexical/topic hybrid; optional vector search is not required for 11F.',
            'Educational summaries paraphrase public guidance — they are not full-text reproductions of copyrighted papers.',
            'Do not invent citations beyond items listed here.',
        ];
        if ($items === []) {
            $limitations[] = 'No relevant knowledge chunks matched this query.';
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'query' => $query,
            'topics' => $topics,
            'item_count' => count($items),
            'items' => $items,
            'limitations' => $limitations,
            'retrieval_backend' => 'lexical_topic_v1',
        ];
    }

    /**
     * @param  array<string, mixed>  $userContext
     */
    private function score(
        KnowledgeChunk $chunk,
        string $query,
        array $topics,
        array $keywords,
        array $userContext,
    ): float {
        $content = mb_strtolower($chunk->content);
        $topic = $chunk->topic;
        $doc = $chunk->document;
        $source = $doc?->source;
        $score = 0.0;

        if (in_array($topic, $topics, true)) {
            $score += 25;
        }
        if ($doc && in_array($doc->topic, $topics, true)) {
            $score += 10;
        }

        $qLower = mb_strtolower($query);
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($content, $kw)) {
                $score += 8;
            }
            if ($kw !== '' && str_contains($qLower, $kw) && str_contains($content, $kw)) {
                $score += 4;
            }
        }

        // Phrase boosts
        $phrases = [
            'progressive overload', 'training to failure', 'proximity to failure',
            'rest interval', 'rest between', 'protein', 'energy balance',
            'hypertrophy', 'strength', 'hydration', 'sleep', 'mma', 'bodyweight',
            'calisthenics', 'range of motion', 'training frequency',
        ];
        foreach ($phrases as $phrase) {
            if (str_contains($qLower, $phrase) && str_contains($content, $phrase)) {
                $score += 15;
            }
        }

        // Evidence-quality weighting (never invent consensus)
        $level = $source?->evidence_level ?? '';
        $score += match ($level) {
            'systematic_review', 'meta_analysis' => 12,
            'position_stand' => 10,
            'official_guidance' => 9,
            'randomized_trial' => 8,
            'review' => 5,
            'educational' => 2,
            default => 0,
        };

        // Light personalization boost — does not override evidence quality
        $goal = mb_strtolower((string) ($userContext['goals']['primary_goal'] ?? $userContext['primary_goal'] ?? ''));
        if ($goal !== '' && str_contains($content, 'hypertrophy') && (str_contains($goal, 'muscle') || str_contains($goal, 'bulk'))) {
            $score += 3;
        }
        if (str_contains($goal, 'mma') && $topic === 'combat') {
            $score += 5;
        }

        return $score;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function hasLexicalHit(KnowledgeChunk $chunk, string $query, array $keywords): bool
    {
        $content = mb_strtolower($chunk->content);
        $qLower = mb_strtolower($query);
        foreach ($keywords as $kw) {
            if (mb_strlen($kw) >= 4 && str_contains($content, $kw)) {
                return true;
            }
        }
        $phrases = [
            'progressive overload', 'training to failure', 'proximity to failure',
            'rest interval', 'protein', 'energy balance', 'hypertrophy', 'hydration',
            'sleep', 'mma', 'bodyweight', 'calisthenics', 'range of motion',
        ];
        foreach ($phrases as $phrase) {
            if (str_contains($qLower, $phrase) && str_contains($content, $phrase)) {
                return true;
            }
        }
        // Shared significant tokens (≥5 chars)
        $qTokens = preg_split('/[^a-z0-9\p{L}]+/u', $qLower) ?: [];
        foreach ($qTokens as $t) {
            if (mb_strlen($t) >= 5 && str_contains($content, $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function toItem(KnowledgeChunk $chunk, float $score): array
    {
        $doc = $chunk->document;
        $source = $doc?->source;
        $meta = is_array($source?->metadata) ? $source->metadata : [];

        return [
            'chunk_id' => $chunk->id,
            'document_id' => $doc?->id,
            'chunk_index' => $chunk->chunk_index,
            'title' => $doc?->title,
            'source' => $source?->name,
            'organization' => $source?->organization,
            'url' => $source?->url, // null when unavailable — never invent
            'publication_year' => $source?->publication_year,
            'version' => $source?->version,
            'evidence_level' => $source?->evidence_level,
            'source_type' => $source?->source_type,
            'reliability_level' => $source?->reliability_level,
            'topic' => $chunk->topic,
            'language' => $doc?->language ?? 'en',
            'content' => $chunk->content,
            'relevance_score' => round(min(1.0, $score / 100.0), 4),
            'raw_score' => round($score, 2),
            'population' => $meta['population'] ?? null,
            'limitations' => $meta['limitations'] ?? null,
            'applicability' => $meta['applicability'] ?? null,
        ];
    }

    /**
     * @param  list<string>  $limitations
     * @return array<string, mixed>
     */
    private function emptyContext(string $query, array $limitations = []): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'query' => $query,
            'topics' => [],
            'item_count' => 0,
            'items' => [],
            'limitations' => $limitations,
            'retrieval_backend' => 'lexical_topic_v1',
        ];
    }
}
