<?php

namespace App\Services\Coach\Knowledge;

/**
 * Phase 11F — detect whether a message warrants knowledge retrieval.
 */
class KnowledgeIntentDetector
{
    public function shouldRetrieve(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        // Evidence / explanatory cues
        if (preg_match('/\b(what is|what does|why|how (do|does|should|much|many)|explain|science|evidence|research|study|studies|progressive overload|training to failure|rest (between|time)|hypertrophy|protein|carbohydrate|hydration|sleep|recovery|mma|conditioning|calisthenics|bodyweight)\b/u', $lower)) {
            return true;
        }

        if (preg_match('/\b(ما هو|ما هي|لماذا|كيف|علم|دليل|بروتين|نوم|استشفاء|تضخيم)\b/u', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $userContext
     * @return array{topics: list<string>, keywords: list<string>, goal: string|null, discipline: string|null}
     */
    public function analyze(string $message, array $userContext = []): array
    {
        $lower = mb_strtolower($message);
        $topics = [];
        $keywords = $this->tokenize($lower);

        $topicMap = [
            'training' => ['hypertrophy', 'strength', 'sets', 'reps', 'rest', 'volume', 'frequency', 'progressive', 'overload', 'failure', 'resistance', 'workout', 'training', 'muscle', 'rom', 'range of motion'],
            'nutrition' => ['protein', 'carb', 'carbohydrate', 'fat', 'calorie', 'calories', 'diet', 'nutrition', 'energy balance', 'meal'],
            'recovery' => ['recovery', 'sleep', 'fatigue', 'rest day', 'overtraining'],
            'hydration' => ['hydration', 'water', 'fluid', 'dehydrat'],
            'combat' => ['mma', 'combat', 'fight', 'striking', 'grappling', 'conditioning'],
            'home' => ['home', 'bodyweight', 'calisthenics', 'no equipment'],
            'sleep' => ['sleep', 'insomnia', 'nap'],
        ];

        foreach ($topicMap as $topic => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        $goal = mb_strtolower((string) (
            $userContext['goals']['primary_goal']
            ?? $userContext['primary_goal']
            ?? ''
        ));
        if ($goal !== '') {
            if (str_contains($goal, 'muscle') || str_contains($goal, 'bulk') || str_contains($goal, 'hypertrophy')) {
                $topics[] = 'training';
                $keywords[] = 'hypertrophy';
            }
            if (str_contains($goal, 'fat') || str_contains($goal, 'cut') || str_contains($goal, 'loss')) {
                $topics[] = 'nutrition';
                $keywords[] = 'energy balance';
            }
            if (str_contains($goal, 'mma') || str_contains($goal, 'combat')) {
                $topics[] = 'combat';
            }
        }

        $discipline = null;
        $loc = mb_strtolower((string) (
            $userContext['training']['training_location']
            ?? $userContext['training_location']
            ?? ''
        ));
        if ($loc === 'home') {
            $topics[] = 'home';
            $discipline = 'home';
        }

        $topics = array_values(array_unique($topics));
        // No default topic — empty means caller/retrieval must rely on keywords only.

        return [
            'topics' => $topics,
            'keywords' => array_values(array_unique($keywords)),
            'goal' => $goal !== '' ? $goal : null,
            'discipline' => $discipline,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $lower): array
    {
        $parts = preg_split('/[^a-z0-9\p{L}]+/u', $lower) ?: [];
        $stop = ['the', 'a', 'an', 'and', 'or', 'to', 'of', 'in', 'for', 'is', 'are', 'what', 'how', 'does', 'should', 'my', 'me', 'i', 'on', 'with'];
        $out = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) < 3 || in_array($p, $stop, true)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }
}
