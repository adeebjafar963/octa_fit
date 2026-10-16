<?php

namespace App\Services\Coach\Nutrition;

use App\Models\NutritionPreference;
use App\Models\User;

/**
 * Phase 11E — persist and merge nutrition preferences (allergies HARD, dislikes SOFT).
 */
class NutritionPreferenceService
{
    public function forUser(User $user): NutritionPreference
    {
        return NutritionPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'allergies' => [],
                'intolerances' => [],
                'disliked' => [],
                'liked' => [],
                'excluded_foods' => [],
                'dietary_pattern' => null,
                'meals_per_day' => null,
                'cost_preference' => null,
            ],
        );
    }

    /**
     * @return array{
     *   liked: list<string>,
     *   disliked: list<string>,
     *   allergies: list<string>,
     *   intolerances: list<string>,
     *   dietary_restrictions: list<string>,
     *   excluded_foods: list<string>,
     *   dietary_pattern: string|null,
     *   meals_per_day: int|null,
     *   cost_preference: string|null
     * }
     */
    public function bucketsForUser(User $user): array
    {
        $pref = $this->forUser($user);

        return [
            'liked' => $pref->liked ?? [],
            'disliked' => $pref->dislikeList(),
            'allergies' => $pref->allergyList(),
            'intolerances' => $pref->intoleranceList(),
            'dietary_restrictions' => array_values(array_filter([
                $pref->dietary_pattern,
            ])),
            'excluded_foods' => $pref->excludedFoodList(),
            'dietary_pattern' => $pref->dietary_pattern,
            'meals_per_day' => $pref->meals_per_day,
            'cost_preference' => $pref->cost_preference,
        ];
    }

    /**
     * Parse preference/allergy statements from a user message and persist merges.
     *
     * @return array{changed: bool, allergies_added: list<string>, dislikes_added: list<string>, exclusions_added: list<string>, meals_per_day: int|null, dietary_pattern: string|null}
     */
    public function applyMessageHints(User $user, string $message): array
    {
        $pref = $this->forUser($user);
        $lower = mb_strtolower($message);
        $allergiesAdded = [];
        $dislikesAdded = [];
        $exclusionsAdded = [];
        $meals = null;
        $pattern = null;

        // Allergies (HARD)
        if (preg_match_all('/\b(?:allergic to|allergy to|allergy:)\s+([a-z][a-z\s\-]{1,40})/iu', $message, $m)) {
            foreach ($m[1] as $raw) {
                $token = $this->cleanToken($raw);
                if ($token !== null) {
                    $allergiesAdded[] = $token;
                }
            }
        }
        if (preg_match('/\b(peanut|peanuts|egg|eggs|dairy|milk|wheat|gluten|soy|fish|shellfish|tree nuts?)\s+allerg/iu', $lower, $m)) {
            $allergiesAdded[] = $this->normalizeAllergen($m[1]);
        }

        // Dislikes (SOFT)
        if (preg_match_all('/\b(?:don\'t like|do not like|hate|dislike)\s+([a-z][a-z\s\-]{1,40})/iu', $message, $m)) {
            foreach ($m[1] as $raw) {
                $token = $this->cleanToken($raw);
                if ($token !== null) {
                    $dislikesAdded[] = $token;
                }
            }
        }

        // Exclusions / replacements
        if (preg_match_all('/\b(?:without|no|remove|exclude|don\'t want|do not want|replace)\s+([a-z][a-z\s\-]{1,40})/iu', $message, $m)) {
            foreach ($m[1] as $raw) {
                $token = $this->cleanToken($raw);
                if ($token === null) {
                    continue;
                }
                // "replace X" → exclude X
                if (preg_match('/\breplace\b/iu', $lower)) {
                    $exclusionsAdded[] = $token;
                } elseif (preg_match('/\b(without|no|remove|exclude|don\'t want|do not want)\b/iu', $lower)) {
                    $exclusionsAdded[] = $token;
                }
            }
        }

        // Dairy pattern phrases
        if (preg_match('/\b(don\'t eat dairy|no dairy|dairy[- ]free|lactose)\b/iu', $lower)) {
            $exclusionsAdded[] = 'dairy';
            $allergiesAdded[] = 'dairy'; // treat explicit "don't eat dairy" as hard restriction for plan safety
        }

        if (preg_match('/\b(\d)\s*meals?\b/iu', $message, $m)) {
            $meals = (int) $m[1];
        }

        if (preg_match('/\b(vegetarian|vegan|halal|omnivore)\b/iu', $lower, $m)) {
            $pattern = mb_strtolower($m[1]);
        }

        if (preg_match('/\b(cheap|cheaper|budget|low[- ]cost)\b/iu', $lower)) {
            $pref->cost_preference = 'budget';
        }

        $changed = false;
        if ($allergiesAdded !== []) {
            $pref->allergies = $this->mergeLists($pref->allergies ?? [], $allergiesAdded);
            $changed = true;
        }
        if ($dislikesAdded !== []) {
            $pref->disliked = $this->mergeLists($pref->disliked ?? [], $dislikesAdded);
            $changed = true;
        }
        if ($exclusionsAdded !== []) {
            $pref->excluded_foods = $this->mergeLists($pref->excluded_foods ?? [], $exclusionsAdded);
            $changed = true;
        }
        if ($meals !== null && $meals >= 2 && $meals <= 6) {
            $pref->meals_per_day = $meals;
            $changed = true;
        }
        if ($pattern !== null) {
            $pref->dietary_pattern = $pattern;
            $changed = true;
        }

        if ($changed || $pref->cost_preference === 'budget') {
            $pref->save();
            $changed = true;
        }

        return [
            'changed' => $changed,
            'allergies_added' => array_values(array_unique($allergiesAdded)),
            'dislikes_added' => array_values(array_unique($dislikesAdded)),
            'exclusions_added' => array_values(array_unique($exclusionsAdded)),
            'meals_per_day' => $pref->meals_per_day,
            'dietary_pattern' => $pref->dietary_pattern,
        ];
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $incoming
     * @return list<string>
     */
    private function mergeLists(array $existing, array $incoming): array
    {
        $out = [];
        foreach (array_merge($existing, $incoming) as $item) {
            $t = mb_strtolower(trim((string) $item));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    private function cleanToken(string $raw): ?string
    {
        $t = mb_strtolower(trim($raw));
        $t = preg_replace('/\b(please|now|anymore|food|foods|in my plan|from the plan)\b/iu', '', $t) ?? $t;
        $t = trim(preg_replace('/[^a-z\s\-]/', '', $t) ?? $t);
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);
        if ($t === '' || mb_strlen($t) < 2) {
            return null;
        }
        // Stop at conjunctions
        $t = preg_replace('/\b(and|or|with|but|,).*$/u', '', $t) ?? $t;
        $t = trim($t);

        return $t !== '' ? $this->normalizeAllergen($t) : null;
    }

    private function normalizeAllergen(string $token): string
    {
        $token = mb_strtolower(trim($token));
        $map = [
            'peanuts' => 'peanut',
            'eggs' => 'egg',
            'milks' => 'dairy',
            'milk' => 'dairy',
            'tree nut' => 'tree_nut',
            'tree nuts' => 'tree_nut',
        ];

        return $map[$token] ?? $token;
    }
}
