<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Coach\Knowledge\KnowledgeChunker;
use App\Services\Coach\Knowledge\KnowledgeIngestionService;
use App\Services\Coach\Knowledge\KnowledgeRetrievalService;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\NutritionFoodSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachKnowledgePhase11fTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::query()->insert([
            ['id' => 1, 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'user', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seed(KnowledgeSeeder::class);
    }

    public function test_source_document_chunk_creation_and_hash_stability(): void
    {
        $this->assertGreaterThan(0, KnowledgeSource::query()->count());
        $this->assertGreaterThan(0, KnowledgeDocument::query()->count());
        $this->assertGreaterThan(0, KnowledgeChunk::query()->count());

        $doc = KnowledgeDocument::query()->firstOrFail();
        $hashes = $doc->chunks()->orderBy('chunk_index')->pluck('content_hash')->all();
        $this->assertNotEmpty($hashes);

        // Re-seed is idempotent — no duplicate documents for same identifier.
        $beforeDocs = KnowledgeDocument::query()->count();
        $beforeChunks = KnowledgeChunk::query()->count();
        $this->seed(KnowledgeSeeder::class);
        $this->assertSame($beforeDocs, KnowledgeDocument::query()->count());
        $this->assertSame($beforeChunks, KnowledgeChunk::query()->count());

        $chunker = app(KnowledgeChunker::class);
        $body = $doc->chunks()->orderBy('chunk_index')->pluck('content')->implode("\n\n");
        // Content hash on document is hash of original body from seeder; chunk hashes stable.
        foreach ($doc->chunks as $chunk) {
            $this->assertSame($chunker->hash($chunk->content), $chunk->content_hash);
        }
    }

    public function test_inactive_sources_and_chunks_excluded(): void
    {
        $source = KnowledgeSource::query()->firstOrFail();
        $source->is_active = false;
        $source->save();

        $retrieval = app(KnowledgeRetrievalService::class);
        $ctx = $retrieval->retrieve('progressive overload hypertrophy', ['training'], ['progressive', 'overload'], [], 5);
        foreach ($ctx['items'] as $item) {
            $this->assertNotSame($source->name, $item['source']);
        }

        $source->is_active = true;
        $source->save();

        $chunk = KnowledgeChunk::query()->firstOrFail();
        $chunk->is_active = false;
        $chunk->save();
        $ctx2 = $retrieval->retrieve($chunk->content, [$chunk->topic], ['protein', 'hypertrophy', 'sleep', 'rest'], [], 10);
        $ids = collect($ctx2['items'])->pluck('chunk_id')->all();
        $this->assertNotContains($chunk->id, $ids);
    }

    public function test_retrieval_topic_relevance_limit_and_ordering(): void
    {
        $retrieval = app(KnowledgeRetrievalService::class);
        $ctx = $retrieval->retrieveForMessage('What is progressive overload?', [], 3);
        $this->assertSame('11f.1', $ctx['schema_version']);
        $this->assertGreaterThan(0, $ctx['item_count']);
        $this->assertLessThanOrEqual(3, $ctx['item_count']);

        $scores = collect($ctx['items'])->pluck('raw_score')->all();
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores);

        $protein = $retrieval->retrieveForMessage('How much protein should athletes eat according to evidence?', [], 5);
        $this->assertGreaterThan(0, $protein['item_count']);
        $joined = mb_strtolower(collect($protein['items'])->pluck('content')->implode(' '));
        $this->assertTrue(str_contains($joined, 'protein'));

        $empty = $retrieval->retrieve('zzzznotatopicqqq', ['training'], ['zzzznotatopicqqq'], [], 5);
        $this->assertSame(0, $empty['item_count']);
        $this->assertNotEmpty($empty['limitations']);
    }

    public function test_source_metadata_and_missing_url(): void
    {
        $withUrl = KnowledgeSource::query()->whereNotNull('url')->firstOrFail();
        $this->assertNotEmpty($withUrl->url);
        $this->assertNotEmpty($withUrl->evidence_level);

        $noUrl = KnowledgeSource::query()->whereNull('url')->first();
        $this->assertNotNull($noUrl, 'Fixture must include at least one source without URL');
        $this->assertNull($noUrl->url);

        $retrieval = app(KnowledgeRetrievalService::class);
        $ctx = $retrieval->retrieve('bodyweight calisthenics home progression', ['home'], ['bodyweight', 'calisthenics', 'home'], [], 5);
        $found = collect($ctx['items'])->first(fn ($i) => ($i['source'] ?? '') === $noUrl->name);
        if ($found !== null) {
            $this->assertArrayHasKey('url', $found);
            $this->assertNull($found['url']);
        }
    }

    public function test_chunker_deterministic(): void
    {
        $chunker = app(KnowledgeChunker::class);
        $text = "Paragraph one about hypertrophy.\n\nParagraph two about rest intervals and sets.\n\nParagraph three about progressive overload.";
        $a = $chunker->chunk($text);
        $b = $chunker->chunk($text);
        $this->assertSame($a, $b);
        $this->assertSame(0, $a[0]['chunk_index']);
    }

    public function test_ingestion_duplicate_prevention(): void
    {
        $ingest = app(KnowledgeIngestionService::class);
        $ingest->upsertSourceWithDocuments([
            'name' => 'Test Unique Source',
            'organization' => 'OCTA FIT Test Org',
            'source_type' => 'educational',
            'evidence_level' => 'educational',
            'url' => null,
            'publication_year' => 2024,
        ], [
            [
                'title' => 'Test doc',
                'topic' => 'training',
                'document_identifier' => 'test-unique-doc',
                'body' => 'Progressive overload increases training demand over time for hypertrophy adaptations.',
            ],
        ]);
        $count1 = KnowledgeDocument::query()->where('document_identifier', 'test-unique-doc')->count();
        $ingest->upsertSourceWithDocuments([
            'name' => 'Test Unique Source',
            'organization' => 'OCTA FIT Test Org',
            'source_type' => 'educational',
            'evidence_level' => 'educational',
            'url' => null,
            'publication_year' => 2024,
        ], [
            [
                'title' => 'Test doc',
                'topic' => 'training',
                'document_identifier' => 'test-unique-doc',
                'body' => 'Progressive overload increases training demand over time for hypertrophy adaptations.',
            ],
        ]);
        $this->assertSame(1, KnowledgeDocument::query()->where('document_identifier', 'test-unique-doc')->count());
        $this->assertSame($count1, 1);
    }

    public function test_coach_api_returns_knowledge_context_for_evidence_question(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Progressive overload means gradually increasing training demand.',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $conv = $this->postJson('/api/coach/conversations', [])->assertCreated();
        $id = $conv->json('data.id');

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'What is progressive overload?',
            'language' => 'en',
        ])->assertCreated();

        $kc = $response->json('data.knowledge_context');
        $this->assertIsArray($kc);
        $this->assertSame('11f.1', $kc['schema_version']);
        $this->assertGreaterThan(0, $kc['item_count']);
        $this->assertArrayHasKey('source', $kc['items'][0]);
        $this->assertArrayHasKey('evidence_level', $kc['items'][0]);
        // Chat prose stays natural; sources live in knowledge_context for the evidence UI.
        $content = (string) $response->json('data.assistant_message.content');
        $this->assertStringContainsString('Progressive overload', $content);
        $this->assertStringNotContainsString('Retrieved sources', $content);
        $this->assertStringNotContainsString('ملخص قائم على مصادر', $content);
    }

    public function test_protein_question_keeps_deterministic_targets_authoritative(): void
    {
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Protein supports muscle remodeling.',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser([
            'weight_kg' => 80,
            'height_cm' => 180,
            'age' => 28,
            'gender' => 'male',
            'training_goal' => 'muscle_gain',
        ]);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations', [])->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'How much protein should I eat?',
            'language' => 'en',
        ])->assertCreated();

        $kc = $response->json('data.knowledge_context');
        $this->assertNotNull($kc['personalized_targets']);
        $this->assertTrue($kc['personalized_targets']['authoritative']);
        $this->assertGreaterThan(0, $kc['personalized_targets']['macro_targets']['protein_g']);
        $this->assertStringContainsString('Deterministic estimate', $response->json('data.assistant_message.content'));
    }

    public function test_training_plan_still_authoritative_with_rag_support(): void
    {
        $this->seed(TrainingSeeder::class);
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'Here is your plan.',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser([
            'training_goal' => 'muscle_gain',
            'fitness_level' => 'intermediate',
            'training_frequency' => 4,
        ]);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations', [])->json('data.id');
        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Give me a 4-day bulking plan.',
            'language' => 'en',
        ])->assertCreated();

        $this->assertNotNull($response->json('data.training_plan'));
        $this->assertSame('11c.1', $response->json('data.training_plan.schema_version'));
        $kc = $response->json('data.knowledge_context');
        $this->assertIsArray($kc);
    }

    public function test_allergy_constraints_not_overridden_by_rag(): void
    {
        $this->seed(NutritionFoodSeeder::class);
        Http::fake([
            '*/chat' => Http::response([
                'success' => true,
                'message' => 'placeholder',
                'provider' => 'mock',
                'language' => 'en',
            ], 200),
        ]);

        $user = $this->makeUser(['weight_kg' => 75, 'height_cm' => 175, 'age' => 25, 'gender' => 'male']);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/coach/conversations', [])->json('data.id');

        // Establish plan then allergy regen — RAG must not put allergens back.
        $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'Create me a 2200 calorie meal plan.',
            'language' => 'en',
        ])->assertCreated();

        $response = $this->postJson("/api/coach/conversations/{$id}/messages", [
            'message' => 'I am allergic to peanuts. What can I eat?',
            'language' => 'en',
        ])->assertCreated();

        $plan = $response->json('data.nutrition_plan');
        if (is_array($plan)) {
            $names = collect($plan['days'] ?? [])
                ->flatMap(fn ($d) => $d['meals'] ?? [])
                ->flatMap(fn ($m) => $m['foods'] ?? [])
                ->pluck('name')
                ->implode(' ');
            $this->assertStringNotContainsStringIgnoringCase('peanut', $names);
            $this->assertStringNotContainsStringIgnoringCase('Protein Bar', $names);
        }
        $allergies = app(\App\Services\UserAiContextBuilder::class)->forCoach($user)['nutrition']['preferences']['allergies'] ?? [];
        $this->assertContains('peanut', $allergies);
    }

    public function test_admin_knowledge_list_endpoints(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        Sanctum::actingAs($admin);

        $sources = $this->getJson('/api/admin/knowledge/sources')->assertOk()->json('data.items');
        $this->assertNotEmpty($sources);
        $this->getJson('/api/admin/knowledge/documents')->assertOk();
        $this->getJson('/api/admin/knowledge/chunks')->assertOk();
    }

    public function test_capabilities_rag_flag(): void
    {
        $user = $this->makeUser();
        $ctx = app(\App\Services\UserAiContextBuilder::class)->forCoach($user);
        $this->assertTrue($ctx['capabilities']['rag']);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function makeUser(array $profile = []): User
    {
        $user = User::factory()->create(['role_id' => 2]);
        Profile::query()->create(array_merge([
            'user_id' => $user->id,
            'age' => 25,
            'gender' => 'male',
            'height_cm' => 175,
            'weight_kg' => 75,
            'locale' => 'en',
            'fitness_level' => 'beginner',
            'training_goal' => 'general_fitness',
            'training_frequency' => 3,
            'meals_per_day' => 4,
            'profile_completed_at' => now(),
        ], $profile));

        return $user->fresh(['profile']);
    }
}
