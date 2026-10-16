<?php

namespace App\Services\Coach;

/**
 * Phase 11C — structured programming rules (extensible; source-ready for 11F).
 *
 * Rules are coaching conventions informed by general resistance-training principles.
 * They are NOT medical prescriptions and do not claim universal superiority of any exercise.
 */
class TrainingKnowledge
{
    /**
     * @return array{sets: int, reps_min: int, reps_max: int, rest_seconds: int, notes: string}
     */
    public function prescriptionFor(string $goal, string $level, string $role = 'compound'): array
    {
        $base = match ($goal) {
            'strength' => [
                'compound' => ['sets' => 4, 'reps_min' => 3, 'reps_max' => 6, 'rest_seconds' => 180],
                'accessory' => ['sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120],
            ],
            'fat_loss' => [
                'compound' => ['sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 75],
                'accessory' => ['sets' => 2, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
            ],
            'conditioning' => [
                'compound' => ['sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'rest_seconds' => 60],
                'accessory' => ['sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 45],
            ],
            'endurance', 'general_fitness', 'mobility' => [
                'compound' => ['sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 60],
                'accessory' => ['sets' => 2, 'reps_min' => 12, 'reps_max' => 20, 'rest_seconds' => 45],
            ],
            default => [ // hypertrophy / muscle gain / bulking training
                'compound' => ['sets' => 3, 'reps_min' => 6, 'reps_max' => 12, 'rest_seconds' => 120],
                'accessory' => ['sets' => 3, 'reps_min' => 8, 'reps_max' => 15, 'rest_seconds' => 75],
            ],
        };

        $slot = $base[$role] ?? $base['compound'];

        if ($level === 'beginner') {
            $slot['sets'] = max(2, $slot['sets'] - 1);
        } elseif ($level === 'advanced' && $goal === 'hypertrophy') {
            $slot['sets'] = min(5, $slot['sets'] + 1);
        }

        $slot['notes'] = $this->intensityNote($goal);
        $slot['evidence_tag'] = 'resistance_training_general';
        $slot['source_ready'] = true; // Phase 11F can attach citations

        return $slot;
    }

    public function intensityNote(string $goal): string
    {
        return match ($goal) {
            'strength' => 'Leave ~1–3 reps in reserve on most sets; prioritize form and progressive load.',
            'fat_loss' => 'Keep technique solid; manage fatigue so you can train consistently while in a calorie deficit (nutrition handled separately).',
            'conditioning' => 'Emphasize quality movement and recoverable intensity; this supports MMA conditioning, not technical fight coaching.',
            default => 'Train most working sets near but not necessarily to failure; progress load or reps over time when form is stable.',
        };
    }

    public function progressionStrategy(string $goal, string $level): string
    {
        return match ($goal) {
            'strength' => 'When you complete the top of the rep range with solid form for all sets, increase load slightly next session.',
            'fat_loss' => 'Prioritize maintaining loads and reps; only reduce volume if recovery declines.',
            default => 'Progressive overload: add reps within the prescribed range, then increase load when the top of the range is achieved with good form. Use regressions if technique breaks down.',
        }.($level === 'beginner'
            ? ' Beginners should master technique before chasing load.'
            : '');
    }

    /**
     * Session templates by split + day index (0-based).
     *
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    public function sessionTemplates(string $split, int $days, string $discipline): array
    {
        if ($discipline === 'mma') {
            return $this->mmaTemplates($days);
        }
        if ($discipline === 'home_calisthenics') {
            return $this->homeTemplates($days);
        }

        return match ($split) {
            'full_body' => $this->fullBodyTemplates($days),
            'upper_lower' => $this->upperLowerTemplates($days),
            'push_pull_legs' => $this->pplTemplates($days),
            default => $this->bodyPartTemplates($days),
        };
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function fullBodyTemplates(int $days): array
    {
        $day = [
            'title' => 'Full Body',
            'focus' => 'full_body',
            'slots' => [
                ['pattern' => 'horizontal_push', 'role' => 'compound'],
                ['pattern' => 'horizontal_pull', 'role' => 'compound'],
                ['pattern' => 'squat', 'role' => 'compound', 'search' => 'squat'],
                ['pattern' => 'hinge', 'role' => 'compound', 'search' => 'deadlift'],
                ['muscle' => 'core', 'role' => 'accessory', 'search' => 'plank'],
            ],
        ];

        return array_fill(0, max(2, min($days, 3)), $day);
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function upperLowerTemplates(int $days): array
    {
        $upper = [
            'title' => 'Upper Body',
            'focus' => 'upper',
            'slots' => [
                ['pattern' => 'horizontal_push', 'role' => 'compound', 'muscle' => 'chest'],
                ['pattern' => 'horizontal_pull', 'role' => 'compound'],
                ['pattern' => 'vertical_push', 'role' => 'compound'],
                ['pattern' => 'vertical_pull', 'role' => 'compound'],
                ['muscle' => 'biceps', 'role' => 'accessory'],
                ['muscle' => 'triceps', 'role' => 'accessory'],
            ],
        ];
        $lower = [
            'title' => 'Lower Body',
            'focus' => 'lower',
            'slots' => [
                ['pattern' => 'squat', 'role' => 'compound', 'search' => 'squat'],
                ['pattern' => 'hinge', 'role' => 'compound', 'search' => 'romanian'],
                ['muscle' => 'quadriceps', 'role' => 'accessory', 'search' => 'leg'],
                ['muscle' => 'hamstrings', 'role' => 'accessory'],
                ['muscle' => 'calves', 'role' => 'accessory', 'search' => 'calf'],
                ['muscle' => 'core', 'role' => 'accessory', 'search' => 'plank'],
            ],
        ];

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $i % 2 === 0 ? $upper : $lower;
        }

        return $out;
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function pplTemplates(int $days): array
    {
        $push = [
            'title' => 'Push',
            'focus' => 'push',
            'slots' => [
                ['pattern' => 'horizontal_push', 'role' => 'compound', 'muscle' => 'chest'],
                ['pattern' => 'vertical_push', 'role' => 'compound'],
                ['muscle' => 'chest', 'role' => 'accessory'],
                ['muscle' => 'triceps', 'role' => 'accessory'],
            ],
        ];
        $pull = [
            'title' => 'Pull',
            'focus' => 'pull',
            'slots' => [
                ['pattern' => 'vertical_pull', 'role' => 'compound'],
                ['pattern' => 'horizontal_pull', 'role' => 'compound'],
                ['muscle' => 'biceps', 'role' => 'accessory'],
                ['muscle' => 'rear deltoids', 'role' => 'accessory', 'search' => 'face pull'],
            ],
        ];
        $legs = [
            'title' => 'Legs',
            'focus' => 'legs',
            'slots' => [
                ['pattern' => 'squat', 'role' => 'compound', 'search' => 'squat'],
                ['pattern' => 'hinge', 'role' => 'compound'],
                ['muscle' => 'quadriceps', 'role' => 'accessory'],
                ['muscle' => 'hamstrings', 'role' => 'accessory'],
                ['muscle' => 'calves', 'role' => 'accessory', 'search' => 'calf'],
            ],
        ];

        $cycle = [$push, $pull, $legs];
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $cycle[$i % 3];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function bodyPartTemplates(int $days): array
    {
        $parts = [
            ['title' => 'Chest / Triceps', 'focus' => 'chest', 'slots' => [
                ['muscle' => 'chest', 'role' => 'compound', 'pattern' => 'horizontal_push'],
                ['muscle' => 'chest', 'role' => 'accessory'],
                ['muscle' => 'triceps', 'role' => 'accessory'],
            ]],
            ['title' => 'Back / Biceps', 'focus' => 'back', 'slots' => [
                ['pattern' => 'vertical_pull', 'role' => 'compound'],
                ['pattern' => 'horizontal_pull', 'role' => 'compound'],
                ['muscle' => 'biceps', 'role' => 'accessory'],
            ]],
            ['title' => 'Legs', 'focus' => 'legs', 'slots' => [
                ['pattern' => 'squat', 'role' => 'compound', 'search' => 'squat'],
                ['pattern' => 'hinge', 'role' => 'compound'],
                ['muscle' => 'calves', 'role' => 'accessory', 'search' => 'calf'],
            ]],
            ['title' => 'Shoulders / Arms', 'focus' => 'shoulders', 'slots' => [
                ['pattern' => 'vertical_push', 'role' => 'compound'],
                ['muscle' => 'side deltoids', 'role' => 'accessory', 'search' => 'lateral'],
                ['muscle' => 'biceps', 'role' => 'accessory'],
                ['muscle' => 'triceps', 'role' => 'accessory'],
            ]],
            ['title' => 'Full Body Accessory', 'focus' => 'full_body', 'slots' => [
                ['pattern' => 'horizontal_push', 'role' => 'compound'],
                ['pattern' => 'horizontal_pull', 'role' => 'compound'],
                ['pattern' => 'squat', 'role' => 'compound', 'search' => 'squat'],
                ['muscle' => 'core', 'role' => 'accessory', 'search' => 'plank'],
            ]],
        ];

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $parts[$i % count($parts)];
        }

        return $out;
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function homeTemplates(int $days): array
    {
        $a = [
            'title' => 'Home Push / Core',
            'focus' => 'push',
            'slots' => [
                ['search' => 'push-up', 'role' => 'compound', 'pattern' => 'horizontal_push'],
                ['search' => 'dip', 'role' => 'compound'],
                ['search' => 'plank', 'role' => 'accessory', 'muscle' => 'core'],
            ],
        ];
        $b = [
            'title' => 'Home Pull / Legs',
            'focus' => 'pull',
            'slots' => [
                ['search' => 'pull-up', 'role' => 'compound', 'pattern' => 'vertical_pull'],
                ['search' => 'squat', 'role' => 'compound'],
                ['search' => 'lunge', 'role' => 'accessory'],
            ],
        ];
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $i % 2 === 0 ? $a : $b;
        }

        return $out;
    }

    /**
     * @return list<array{title: string, focus: string, slots: list<array{muscle?: string, pattern?: string, role: string, search?: string}>}>
     */
    private function mmaTemplates(int $days): array
    {
        $a = [
            'title' => 'MMA Strength / Power',
            'focus' => 'strength',
            'slots' => [
                ['search' => 'jab', 'role' => 'compound'],
                ['search' => 'cross', 'role' => 'compound'],
                ['search' => 'kick', 'role' => 'compound'],
                ['search' => 'hook', 'role' => 'compound'],
                ['search' => 'push-up', 'role' => 'accessory'],
                ['search' => 'sprawl', 'role' => 'accessory'],
                ['search' => 'knee', 'role' => 'accessory'],
                ['search' => 'uppercut', 'role' => 'accessory'],
                ['search' => 'burpee', 'role' => 'accessory'],
                ['search' => 'roundhouse', 'role' => 'accessory'],
            ],
        ];
        $b = [
            'title' => 'MMA Conditioning',
            'focus' => 'conditioning',
            'slots' => [
                ['search' => 'uppercut', 'role' => 'compound'],
                ['search' => 'hook', 'role' => 'compound'],
                ['search' => 'sprawl', 'role' => 'compound'],
                ['search' => 'jab', 'role' => 'compound'],
                ['search' => 'burpee', 'role' => 'accessory'],
                ['search' => 'knee', 'role' => 'accessory'],
                ['search' => 'kick', 'role' => 'accessory'],
                ['search' => 'cross', 'role' => 'accessory'],
                ['search' => 'push-up', 'role' => 'accessory'],
                ['search' => 'roundhouse', 'role' => 'accessory'],
            ],
        ];
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[] = $i % 2 === 0 ? $a : $b;
        }

        return $out;
    }

    /**
     * Scale exercise count toward longer requested sessions.
     */
    public function exercisesPerSession(string $level, int $targetDurationMinutes = 60): int
    {
        $base = match ($level) {
            'beginner' => 4,
            'advanced' => 6,
            default => 5,
        };

        if ($targetDurationMinutes >= 120) {
            return min(10, $base + 4);
        }
        if ($targetDurationMinutes >= 90) {
            return min(8, $base + 2);
        }

        return $base;
    }

    /**
     * Approximate minutes — not an exact stopwatch claim.
     */
    public function estimateSessionMinutes(int $exerciseCount, int $avgSets, int $avgRest, int $targetDuration = 60): int
    {
        $work = $exerciseCount * $avgSets * 0.75; // ~45s effort/set
        $rest = $exerciseCount * max(0, $avgSets - 1) * ($avgRest / 60);
        $transitions = $exerciseCount * 1.5;
        $estimated = (int) round($work + $rest + $transitions + 5);

        // Prefer converging toward an explicit user duration without inventing fake work.
        if ($targetDuration >= 90) {
            $estimated = (int) round(($estimated * 0.35) + ($targetDuration * 0.65));
        } elseif ($targetDuration > 0) {
            $estimated = (int) round(($estimated + $targetDuration) / 2);
        }

        return (int) max(25, min(180, $estimated));
    }
}
