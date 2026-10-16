<?php

namespace App\Services\Coach\Memory;

use App\Models\CoachMemory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11H — persistent Coach memory CRUD, limits, dedupe, context packing.
 * Laravel is the source of truth. FastAPI only receives filtered context.
 */
class CoachMemoryService
{
    public const SCHEMA_VERSION = '11h.1';

    public const MAX_ACTIVE_TOTAL = 40;

    public const MAX_ACTIVE_PER_CATEGORY = 8;

    public const MAX_CONTEXT_ITEMS = 12;

    /**
     * Upsert a confirmed memory by (user, category, key). Replaces value safely.
     *
     * @param  array<string, mixed>|list<mixed>|string|int|float|bool  $value
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        User $user,
        string $category,
        string $key,
        mixed $value,
        string $source = CoachMemory::SOURCE_EXPLICIT,
        float $confidence = 1.0,
        bool $explicit = true,
        ?\DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): CoachMemory {
        $category = $this->normalizeCategory($category);
        $key = $this->normalizeKey($key);
        $normalizedValue = $this->normalizeValue($value);
        $now = now();

        $memory = CoachMemory::query()->firstOrNew([
            'user_id' => $user->id,
            'category' => $category,
            'key' => $key,
        ]);

        $isNew = ! $memory->exists;
        $memory->value = $normalizedValue;
        $memory->source = $source === CoachMemory::SOURCE_INFERRED
            ? CoachMemory::SOURCE_INFERRED
            : CoachMemory::SOURCE_EXPLICIT;
        $memory->confidence = max(0.0, min(1.0, $confidence));
        $memory->explicit = $explicit;
        $memory->active = true;
        $memory->first_seen_at = $memory->first_seen_at ?? $now;
        $memory->last_confirmed_at = $now;
        $memory->expires_at = $expiresAt;
        $memory->metadata = array_merge($memory->metadata ?? [], $metadata, [
            'updated_via' => $isNew ? 'create' : 'update',
        ]);
        $memory->save();

        $this->enforceLimits($user);

        return $memory->fresh();
    }

    /**
     * Deactivate a memory belonging to the user.
     */
    public function deactivate(User $user, CoachMemory $memory): CoachMemory
    {
        if ((int) $memory->user_id !== (int) $user->id) {
            abort(403);
        }

        $memory->active = false;
        $meta = $memory->metadata ?? [];
        $meta['deactivated_at'] = now()->toIso8601String();
        $memory->metadata = $meta;
        $memory->save();

        return $memory;
    }

    public function delete(User $user, CoachMemory $memory): void
    {
        if ((int) $memory->user_id !== (int) $user->id) {
            abort(403);
        }
        $memory->delete();
    }

    public function clearAll(User $user): int
    {
        return CoachMemory::query()->forUser($user)->delete();
    }

    /**
     * @return Collection<int, CoachMemory>
     */
    public function listActive(User $user): Collection
    {
        $this->expireStale($user);

        return CoachMemory::query()
            ->forUser($user)
            ->active()
            ->orderBy('category')
            ->orderByDesc('last_confirmed_at')
            ->get();
    }

    /**
     * Build compact memory_context (schema 11h.1) filtered by message relevance.
     *
     * @return array{schema_version: string, memories: list<array<string, mixed>>, count: int, authority_note: string}
     */
    public function buildContext(User $user, ?string $message = null): array
    {
        $this->expireStale($user);

        $categories = $this->relevantCategories($message);
        $query = CoachMemory::query()
            ->forUser($user)
            ->active()
            ->where('explicit', true)
            ->where('confidence', '>=', 0.8)
            ->orderByDesc('last_confirmed_at')
            ->orderBy('category')
            ->orderBy('key');

        if ($categories !== null) {
            $query->whereIn('category', $categories);
        }

        $items = $query->limit(self::MAX_CONTEXT_ITEMS)->get()
            ->map(fn (CoachMemory $m) => $m->toContextItem())
            ->values()
            ->all();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'memories' => $items,
            'count' => count($items),
            'authority_note' => 'supporting_context_current_request_and_profile_win',
        ];
    }

    /**
     * Read a single active memory value helper.
     */
    public function getActiveValue(User $user, string $category, string $key): mixed
    {
        $row = CoachMemory::query()
            ->forUser($user)
            ->active()
            ->where('category', $this->normalizeCategory($category))
            ->where('key', $this->normalizeKey($key))
            ->first();

        return $row?->value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function memoryHintsForTraining(array $memoryContext): array
    {
        $out = [];
        foreach ($memoryContext['memories'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $cat = (string) ($item['category'] ?? '');
            if (in_array($cat, [CoachMemory::CATEGORY_EQUIPMENT, CoachMemory::CATEGORY_TRAINING, CoachMemory::CATEGORY_SCHEDULE, CoachMemory::CATEGORY_GOAL], true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    public function expireStale(User $user): int
    {
        return CoachMemory::query()
            ->forUser($user)
            ->where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'active' => false,
                'updated_at' => now(),
            ]);
    }

    private function enforceLimits(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach (CoachMemory::CATEGORIES as $category) {
                $ids = CoachMemory::query()
                    ->forUser($user)
                    ->active()
                    ->where('category', $category)
                    ->orderByDesc('last_confirmed_at')
                    ->orderByDesc('id')
                    ->pluck('id');

                if ($ids->count() > self::MAX_ACTIVE_PER_CATEGORY) {
                    $drop = $ids->slice(self::MAX_ACTIVE_PER_CATEGORY)->values();
                    CoachMemory::query()->whereIn('id', $drop)->update(['active' => false]);
                }
            }

            $ids = CoachMemory::query()
                ->forUser($user)
                ->active()
                ->orderByDesc('last_confirmed_at')
                ->orderByDesc('id')
                ->pluck('id');

            if ($ids->count() > self::MAX_ACTIVE_TOTAL) {
                $drop = $ids->slice(self::MAX_ACTIVE_TOTAL)->values();
                CoachMemory::query()->whereIn('id', $drop)->update(['active' => false]);
            }
        });
    }

    /**
     * @return list<string>|null  null = all categories (high-value only still limited)
     */
    private function relevantCategories(?string $message): ?array
    {
        if ($message === null || trim($message) === '') {
            return [
                CoachMemory::CATEGORY_GOAL,
                CoachMemory::CATEGORY_COACHING,
                CoachMemory::CATEGORY_TRAINING,
                CoachMemory::CATEGORY_EQUIPMENT,
            ];
        }

        $lower = mb_strtolower($message);
        $cats = [CoachMemory::CATEGORY_GOAL, CoachMemory::CATEGORY_COACHING];

        $nutrition = (bool) preg_match(
            '/\b(food|meal|diet|calorie|protein|nutrition|eat|tuna|chicken|allergy|allerg|خطة غذ|وجبة|سعرات|بروتين|تغذية)\b/u',
            $lower,
        );
        $training = (bool) preg_match(
            '/\b(workout|train|exercise|program|plan|gym|dumbbell|home|muscle|strength|mma|تمرين|تدريب|خطة|صالة|دمبل)\b/u',
            $lower,
        );

        if ($nutrition) {
            $cats[] = CoachMemory::CATEGORY_FOOD;
            $cats[] = CoachMemory::CATEGORY_NUTRITION;
        }
        if ($training || ! $nutrition) {
            $cats[] = CoachMemory::CATEGORY_TRAINING;
            $cats[] = CoachMemory::CATEGORY_EQUIPMENT;
            $cats[] = CoachMemory::CATEGORY_SCHEDULE;
        }

        return array_values(array_unique($cats));
    }

    private function normalizeCategory(string $category): string
    {
        $c = mb_strtolower(trim($category));
        if (! in_array($c, CoachMemory::CATEGORIES, true)) {
            return CoachMemory::CATEGORY_PROFILE;
        }

        return $c;
    }

    private function normalizeKey(string $key): string
    {
        $k = mb_strtolower(trim($key));
        $k = preg_replace('/[^a-z0-9_\-]+/u', '_', $k) ?? $k;
        $k = trim($k, '_');

        return mb_substr($k !== '' ? $k : 'preference', 0, 128);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function normalizeValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return ['text' => $value === null ? null : (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)];
        }

        return ['text' => null];
    }
}
