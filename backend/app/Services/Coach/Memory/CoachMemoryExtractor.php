<?php

namespace App\Services\Coach\Memory;

use App\Models\CoachMemory;
use App\Models\User;
use App\Services\Coach\Nutrition\NutritionPreferenceService;

/**
 * Phase 11H — conservative memory extraction from USER messages only.
 * Temporary requests, questions, jokes, and uncertain language do not create confirmed memory.
 * Allergies / food dislikes route through NutritionPreferenceService (11E authoritative).
 */
class CoachMemoryExtractor
{
    public function __construct(
        private readonly CoachMemoryService $memories,
        private readonly NutritionPreferenceService $nutritionPreferences,
    ) {}

    /**
     * @return array{
     *   changed: bool,
     *   created: list<string>,
     *   updated: list<string>,
     *   deactivated: list<string>,
     *   nutrition_synced: bool,
     *   skipped_reason: string|null
     * }
     */
    public function processUserMessage(User $user, string $message): array
    {
        $message = trim($message);
        $result = [
            'changed' => false,
            'created' => [],
            'updated' => [],
            'deactivated' => [],
            'nutrition_synced' => false,
            'skipped_reason' => null,
        ];

        if ($message === '') {
            $result['skipped_reason'] = 'empty';

            return $result;
        }

        // Never invent memory from assistant-like quoting or hypothetical framing alone.
        if ($this->isQuestionOnly($message)) {
            $result['skipped_reason'] = 'question';

            return $result;
        }

        if ($this->isUncertain($message)) {
            $result['skipped_reason'] = 'uncertain';

            return $result;
        }

        if ($this->isTemporaryAction($message)) {
            $result['skipped_reason'] = 'temporary_action';

            return $result;
        }

        // 11E authoritative path for allergies / durable food prefs (explicit dislike language).
        if ($this->isDurableFoodPreferenceStatement($message)) {
            $hints = $this->nutritionPreferences->applyMessageHints($user, $message);
            if ($hints['changed'] ?? false) {
                $result['nutrition_synced'] = true;
                $result['changed'] = true;
                foreach ($hints['dislikes_added'] ?? [] as $food) {
                    $this->rememberFoodList($user, 'disliked_foods', (string) $food, $result);
                }
                foreach ($hints['allergies_added'] ?? [] as $food) {
                    // Record a soft reference only — allergies stay in nutrition_preferences.
                    $this->memories->upsert(
                        $user,
                        CoachMemory::CATEGORY_NUTRITION,
                        'allergy_ref_'.preg_replace('/[^a-z0-9]+/u', '_', mb_strtolower((string) $food)),
                        ['food' => mb_strtolower((string) $food), 'authority' => 'nutrition_preferences'],
                        CoachMemory::SOURCE_EXPLICIT,
                        1.0,
                        true,
                        null,
                        ['note' => 'reference_only_11e_authoritative'],
                    );
                    $result['created'][] = 'nutrition_preference:allergy_ref';
                }
            }
        }

        // Permanent exclusion language ("from now on…")
        if (preg_match('/\b(from now on|going forward|permanently|always)\b.+\b(don\'t|do not|no|without|exclude)\b/iu', $message)
            || preg_match('/\b(don\'t|do not)\s+(?:ever\s+)?(?:include|give me|put)\b/iu', $message)) {
            $hints = $this->nutritionPreferences->applyMessageHints($user, $message);
            if ($hints['changed'] ?? false) {
                $result['nutrition_synced'] = true;
                $result['changed'] = true;
            }
        }

        // Equipment
        if (preg_match('/\b(i only have|only have|just have)\b.{0,40}\b(dumbbells?|bands?|resistance bands?|barbell|kettlebells?)\b/iu', $message, $m)
            || preg_match('/\b(dumbbells?\s+only|home\s+only|only\s+dumbbells?)\b/iu', $message)) {
            $eq = $this->detectEquipmentToken($message) ?? 'dumbbells';
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_EQUIPMENT,
                'available_equipment',
                ['items' => [$eq], 'mode' => $eq === 'dumbbells' || $eq === 'dumbbell' ? 'dumbbell' : 'home'],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            // Supersede conflicting gym-only memory
            $this->deactivateKey($user, CoachMemory::CATEGORY_EQUIPMENT, 'gym_access', $result);
            $result['changed'] = true;
            $result['created'][] = 'equipment:available_equipment';
        }

        if (preg_match('/\b(i (?:just )?joined a gym|i (?:have|got) (?:a )?gym|gym access|full gym)\b/iu', $message)) {
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_EQUIPMENT,
                'gym_access',
                ['mode' => 'gym', 'items' => ['gym']],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $this->deactivateKey($user, CoachMemory::CATEGORY_EQUIPMENT, 'available_equipment', $result);
            $result['changed'] = true;
            $result['created'][] = 'equipment:gym_access';
        }

        if (preg_match('/\b(i (?:only )?train at home|home workouts? only|prefer home workouts?)\b/iu', $message)) {
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_TRAINING,
                'environment',
                ['text' => 'home'],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $result['changed'] = true;
            $result['created'][] = 'training_preference:environment';
        }

        // Schedule — explicit habitual language only
        if (preg_match('/\b(i usually train|i (?:always|typically) train|i train at)\b.{0,20}\b(\d{1,2})\s*(?::|\.)?(\d{2})?\s*(am|pm)?\b/iu', $message, $m)
            || preg_match('/\b(i usually train|i train)\b.{0,20}\b(morning|evening|night|after work)\b/iu', $message, $m2)) {
            $value = isset($m[2])
                ? ['hour' => (int) $m[2], 'minute' => isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0, 'ampm' => $m[4] ?? null]
                : ['slot' => mb_strtolower((string) ($m2[2] ?? 'morning'))];
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_SCHEDULE,
                'preferred_training_time',
                $value,
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $result['changed'] = true;
            $result['created'][] = 'schedule:preferred_training_time';
        }

        // Training frequency preference (stable phrasing)
        if (preg_match('/\b(i (?:prefer|usually|always) (?:train|training)|prefer)\b.{0,30}\b([2-6])\s*(?:training\s+)?days?\b/iu', $message, $m)
            || preg_match('/\b([2-6])\s*training days?\s*(?:a|per)\s*week\b/iu', $message, $m)) {
            $days = (int) ($m[2] ?? $m[1]);
            if ($days >= 2 && $days <= 6) {
                $this->memories->upsert(
                    $user,
                    CoachMemory::CATEGORY_TRAINING,
                    'days_per_week',
                    ['days' => $days],
                    CoachMemory::SOURCE_EXPLICIT,
                    1.0,
                    true,
                );
                $result['changed'] = true;
                $result['created'][] = 'training_preference:days_per_week';
            }
        }

        // Shorter sessions preference
        if (preg_match('/\b(prefer|usually)\b.{0,40}\b(short(?:er)? sessions?|(\d{2})\s*min)/iu', $message, $m)) {
            $mins = isset($m[3]) ? (int) $m[3] : 30;
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_TRAINING,
                'session_duration_minutes',
                ['minutes' => min(120, max(20, $mins))],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $result['changed'] = true;
            $result['created'][] = 'training_preference:session_duration_minutes';
        }

        // Goal memory (supporting only — current request still wins in engines)
        if (preg_match('/\b(my goal is|i want to focus on|long[- ]term goal)\b.{0,40}\b(muscle|hypertrophy|bulk|fat loss|cut|strength|conditioning|mma|fitness)\b/iu', $message, $m)) {
            $goal = $this->mapGoalToken($m[2] ?? '');
            if ($goal !== null) {
                $this->memories->upsert(
                    $user,
                    CoachMemory::CATEGORY_GOAL,
                    'primary_goal',
                    ['goal' => $goal],
                    CoachMemory::SOURCE_EXPLICIT,
                    1.0,
                    true,
                );
                $result['changed'] = true;
                $result['created'][] = 'goal:primary_goal';
            }
        }

        // Coaching style
        if (preg_match('/\b(prefer|please)\b.{0,30}\b(concise|short|brief)\b.{0,20}\b(answers?|replies|responses?)?\b/iu', $message)) {
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_COACHING,
                'answer_style',
                ['style' => 'concise'],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $result['changed'] = true;
            $result['created'][] = 'coaching_preference:answer_style';
        } elseif (preg_match('/\b(prefer|please)\b.{0,30}\b(detailed|in[- ]depth|thorough)\b.{0,20}\b(answers?|explanations?)?\b/iu', $message)) {
            $this->memories->upsert(
                $user,
                CoachMemory::CATEGORY_COACHING,
                'answer_style',
                ['style' => 'detailed'],
                CoachMemory::SOURCE_EXPLICIT,
                1.0,
                true,
            );
            $result['changed'] = true;
            $result['created'][] = 'coaching_preference:answer_style';
        }

        // Explicit like-now supersedes dislike
        if (preg_match('/\b(i (?:actually )?like|i (?:do )?like)\s+([a-z][a-z\s\-]{1,30})\s+now\b/iu', $message, $m)) {
            $food = $this->cleanFoodToken($m[2] ?? '');
            if ($food !== null) {
                $this->deactivateKey($user, CoachMemory::CATEGORY_FOOD, 'dislike_'.$food, $result);
                $pref = $this->nutritionPreferences->forUser($user);
                $disliked = array_values(array_filter(
                    $pref->dislikeList(),
                    static fn (string $d) => $d !== $food,
                ));
                $pref->disliked = $disliked;
                $liked = $pref->liked ?? [];
                if (! in_array($food, $liked, true)) {
                    $liked[] = $food;
                }
                $pref->liked = $liked;
                $pref->save();
                $this->memories->upsert(
                    $user,
                    CoachMemory::CATEGORY_FOOD,
                    'like_'.$food,
                    ['food' => $food, 'polarity' => 'like'],
                    CoachMemory::SOURCE_EXPLICIT,
                    1.0,
                    true,
                );
                $result['changed'] = true;
                $result['nutrition_synced'] = true;
            }
        }

        if (! $result['changed']) {
            $result['skipped_reason'] = $result['skipped_reason'] ?? 'no_stable_preference';
        }

        return $result;
    }

    /**
     * @param  array{created: list<string>, changed: bool}  $result
     */
    private function rememberFoodList(User $user, string $listKey, string $food, array &$result): void
    {
        $food = mb_strtolower(trim($food));
        if ($food === '') {
            return;
        }
        $this->memories->upsert(
            $user,
            CoachMemory::CATEGORY_FOOD,
            'dislike_'.$food,
            ['food' => $food, 'polarity' => 'dislike', 'list' => $listKey, 'authority' => 'nutrition_preferences'],
            CoachMemory::SOURCE_EXPLICIT,
            1.0,
            true,
            null,
            ['note' => 'mirrored_from_11e'],
        );
        $result['created'][] = 'food_preference:dislike_'.$food;
    }

    /**
     * @param  array{deactivated: list<string>}  $result
     */
    private function deactivateKey(User $user, string $category, string $key, array &$result): void
    {
        $row = CoachMemory::query()
            ->forUser($user)
            ->where('category', $category)
            ->where('key', $key)
            ->where('active', true)
            ->first();
        if ($row !== null) {
            $this->memories->deactivate($user, $row);
            $result['deactivated'][] = $category.':'.$key;
        }
    }

    private function isQuestionOnly(string $message): bool
    {
        $t = trim($message);
        if (str_ends_with($t, '?') && ! preg_match('/\b(i hate|i don\'t like|i prefer|i usually|i only have)\b/iu', $t)) {
            return true;
        }

        return (bool) preg_match('/^(how|what|why|when|where|who|كيف|ما|لماذا|متى|أين)\b/iu', $t)
            && ! preg_match('/\b(i hate|i don\'t like|i prefer|i usually|i only have)\b/iu', $t);
    }

    private function isUncertain(string $message): bool
    {
        return (bool) preg_match(
            '/\b(might|maybe|thinking about|considering|possibly|not sure|i think i|قد|ربما|ممكن)\b/iu',
            $message,
        );
    }

    private function isTemporaryAction(string $message): bool
    {
        // One-off meal/plan edits must not become permanent dislikes via memory.
        if (preg_match('/\breplace\b/iu', $message) && preg_match('/\b(this meal|today|for (?:this|the) plan|in (?:this|my) meal)\b/iu', $message)) {
            return true;
        }
        if (preg_match('/\breplace\b.+\bin this\b/iu', $message)) {
            return true;
        }
        if (preg_match('/\b(just for today|this time only|for now only)\b/iu', $message)) {
            return true;
        }

        return false;
    }

    private function isDurableFoodPreferenceStatement(string $message): bool
    {
        if ($this->isTemporaryAction($message)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(i hate|i don\'t like|i do not like|i dislike|allergic to|allergy to|from now on.{0,40}(don\'t|do not|no))\b/iu',
            $message,
        );
    }

    private function detectEquipmentToken(string $message): ?string
    {
        $lower = mb_strtolower($message);
        if (preg_match('/\bdumbbells?\b/u', $lower)) {
            return 'dumbbells';
        }
        if (preg_match('/\b(resistance )?bands?\b/u', $lower)) {
            return 'resistance_bands';
        }
        if (preg_match('/\bbarbell\b/u', $lower)) {
            return 'barbell';
        }
        if (preg_match('/\bkettlebells?\b/u', $lower)) {
            return 'kettlebells';
        }

        return null;
    }

    private function mapGoalToken(string $token): ?string
    {
        $t = mb_strtolower(trim($token));

        return match (true) {
            str_contains($t, 'muscle') || str_contains($t, 'hypertrophy') || str_contains($t, 'bulk') => 'hypertrophy',
            str_contains($t, 'fat') || str_contains($t, 'cut') => 'fat_loss',
            str_contains($t, 'strength') => 'strength',
            str_contains($t, 'mma') || str_contains($t, 'condition') => 'conditioning',
            str_contains($t, 'fitness') => 'general_fitness',
            default => null,
        };
    }

    private function cleanFoodToken(string $raw): ?string
    {
        $t = mb_strtolower(trim($raw));
        $t = preg_replace('/\b(now|anymore|again)\b/u', '', $t) ?? $t;
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        if (mb_strlen($t) < 2 || mb_strlen($t) > 40) {
            return null;
        }

        return $t;
    }
}
