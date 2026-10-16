<?php

namespace App\Services\Physio\Demo;

/**
 * Matches structured physio assessment input to a curated graduation demo case.
 * Isolated — used only when PHYSIO_DEMO_MODE is enabled.
 */
class PhysioDemoCaseResolver
{
    public function __construct(
        private readonly PhysioDemoCaseCatalog $catalog,
    ) {}

    /**
     * @param  array{
     *   body_area?: string|null,
     *   symptoms?: string|null,
     *   pain_level?: int|null,
     *   duration?: string|null,
     *   onset?: string|null,
     *   trigger?: string|null,
     *   activity?: string|null,
     *   additional_information?: string|null
     * }  $input
     * @return array<string, mixed>|null
     */
    public function resolve(array $input): ?array
    {
        $blob = $this->blob($input);

        // Deterministic marker: [PHYS-XX] or CASE:PHYS-XX or bare PHYS-XX
        if (preg_match('/\[PHYS-(\d{2})\]|CASE:PHYS-(\d{2})|\bPHYS-(\d{2})\b/i', $blob, $m) === 1) {
            $num = (int) (($m[1] !== '' ? $m[1] : null) ?? ($m[2] !== '' ? $m[2] : $m[3]));
            $case = $this->catalog->byId($num);
            if ($case !== null) {
                return $case;
            }
        }

        $best = null;
        $bestScore = 0;

        foreach ($this->catalog->all() as $case) {
            $score = $this->score($case, $input, $blob);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $case;
            }
        }

        // Strong match for exact/near-exact form fill.
        if ($bestScore >= 8) {
            return $best;
        }

        return null;
    }

    /**
     * Graduation fallback: when demo mode is on, never drop to generic AI.
     * Picks the best body-region candidate using keywords + pain.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    public function resolveForDemoFallback(array $input): ?array
    {
        $exact = $this->resolve($input);
        if ($exact !== null) {
            return $exact;
        }

        $body = strtolower(trim((string) ($input['body_area'] ?? '')));
        if ($body === '') {
            return $this->catalog->byId(1);
        }

        $blob = $this->blob($input);
        $best = null;
        $bestScore = -1;

        foreach ($this->catalog->all() as $case) {
            $caseBody = strtolower(trim((string) ($case['body_area'] ?? '')));
            if ($caseBody !== $body) {
                continue;
            }
            $score = $this->score($case, $input, $blob);
            // Prefer non-urgent for mild presentations unless red-flag keywords present.
            if ($this->hasRedFlagKeywords($blob) && strtoupper((string) $case['escalation_level']) === 'URGENT') {
                $score += 5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $case;
            }
        }

        if ($best !== null) {
            return $best;
        }

        // Body synonym / nearest region defaults for graduation demos.
        $defaults = [
            'neck' => 1,
            'lower_back' => 6,
            'shoulder' => 11,
            'knee' => 19,
            'ankle' => 27,
            'foot' => 30,
            'hip' => 33,
            'legs' => 38,
            'elbow' => 40,
            'upper_back' => 45,
            'other' => 50,
        ];

        $id = $defaults[$body] ?? 1;

        return $this->catalog->byId($id);
    }

    private function hasRedFlagKeywords(string $blob): bool
    {
        $needles = [
            'saddle', 'cauda', 'fever', 'unable to bear', 'cannot bear', 'weakness',
            'dizziness after trauma', 'numbness', 'locking', 'night sweats',
            'تنميل السرج', 'ضعف', 'حمى', 'عدم تحمل الوزن', 'دوار', 'إصابة',
        ];
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($blob, $this->norm($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $input
     */
    private function score(array $case, array $input, string $blob): int
    {
        $canonical = $case['input'] ?? [];
        $score = 0;

        $body = strtolower(trim((string) ($input['body_area'] ?? '')));
        $caseBody = strtolower(trim((string) ($canonical['body_area'] ?? $case['body_area'] ?? '')));
        if ($body === '' || $caseBody === '' || $body !== $caseBody) {
            return 0;
        }
        $score += 4;

        $symptoms = $this->norm((string) ($input['symptoms'] ?? ''));
        $caseSymptoms = $this->norm((string) ($canonical['symptoms'] ?? ''));
        $symptomsBare = $this->stripMarkers($symptoms);
        $caseSymptomsBare = $this->stripMarkers($caseSymptoms);
        if ($caseSymptoms !== '' && ($symptoms === $caseSymptoms || $symptomsBare === $caseSymptomsBare)) {
            $score += 6;
        } elseif ($caseSymptomsBare !== '' && str_contains($symptomsBare, $caseSymptomsBare)) {
            $score += 4;
        } else {
            foreach ($this->keywordsFor($case) as $needle) {
                if ($needle !== '' && str_contains($blob, $needle)) {
                    $score += 1;
                }
            }
        }

        $pain = $input['pain_level'] ?? null;
        $casePain = $canonical['pain_level'] ?? null;
        if (is_numeric($pain) && is_numeric($casePain) && (int) $pain === (int) $casePain) {
            $score += 2;
        } elseif (is_numeric($pain) && is_numeric($casePain) && abs((int) $pain - (int) $casePain) <= 1) {
            $score += 1;
        }

        $trigger = $this->norm((string) ($input['trigger'] ?? ''));
        $caseTrigger = $this->norm((string) ($canonical['trigger'] ?? ''));
        if ($caseTrigger !== '' && $trigger === $caseTrigger) {
            $score += 3;
        } elseif ($caseTrigger !== '' && $trigger !== '' && str_contains($trigger, $caseTrigger)) {
            $score += 2;
        }

        $duration = $this->norm((string) ($input['duration'] ?? ''));
        $caseDuration = $this->norm((string) ($canonical['duration'] ?? ''));
        if ($caseDuration !== '' && $duration === $caseDuration) {
            $score += 2;
        }

        $additional = $this->norm((string) ($input['additional_information'] ?? ''));
        $caseAdditional = $this->norm((string) ($canonical['additional_information'] ?? ''));
        if ($caseAdditional !== '' && ($additional === $caseAdditional || str_contains($additional, $this->stripMarkers($caseAdditional)))) {
            $score += 3;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $case
     * @return list<string>
     */
    private function keywordsFor(array $case): array
    {
        $canonical = $case['input'] ?? [];
        $raw = [
            (string) ($canonical['trigger'] ?? ''),
            (string) ($canonical['duration'] ?? ''),
            (string) ($case['expected_clinical_pattern'] ?? ''),
            (string) ($canonical['symptoms'] ?? ''),
        ];

        // Common EN/AR demo keywords by body.
        $body = strtolower((string) ($case['body_area'] ?? ''));
        $extra = match ($body) {
            'neck' => ['neck', 'رقبة', 'computer', 'حاسوب', 'phone', 'هاتف', 'headache', 'صداع', 'numbness', 'تنميل'],
            'lower_back' => ['back', 'ظهر', 'lifting', 'رفع', 'sitting', 'جلوس', 'leg', 'ساق', 'saddle', 'ضعف'],
            'shoulder' => ['shoulder', 'كتف', 'overhead', 'overhead', 'press', 'ضغط', 'night', 'ليل'],
            'knee' => ['knee', 'ركبة', 'running', 'جري', 'stairs', 'درج', 'twist', 'التواء', 'locking', 'قفل'],
            'ankle', 'foot' => ['ankle', 'كاحل', 'sprain', 'التواء', 'heel', 'كعب', 'plantar', 'أخمص'],
            'hip' => ['hip', 'ورك', 'side-lying', 'squat', 'قرفصاء'],
            'legs' => ['achilles', 'أخيل', 'hamstring', 'أوتار', 'calf', 'سمانة', 'sprint', 'عدو'],
            'elbow' => ['elbow', 'مرفق', 'grip', 'قبضة', 'tennis', 'تنس'],
            'upper_back' => ['chest', 'صدر', 'bench', 'بنش'],
            default => ['pain', 'ألم', 'fever', 'حمى'],
        };

        $needles = [];
        foreach (array_merge($raw, $extra) as $item) {
            $n = $this->norm($this->stripMarkers($item));
            if ($n !== '') {
                $needles[] = $n;
            }
        }

        return array_values(array_unique($needles));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function blob(array $input): string
    {
        return $this->norm(implode(' ', [
            (string) ($input['body_area'] ?? ''),
            (string) ($input['symptoms'] ?? ''),
            (string) ($input['duration'] ?? ''),
            (string) ($input['onset'] ?? ''),
            (string) ($input['trigger'] ?? ''),
            (string) ($input['activity'] ?? ''),
            (string) ($input['additional_information'] ?? ''),
        ]));
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    private function stripMarkers(string $value): string
    {
        $value = preg_replace('/\[phys-\d{2}\]/i', '', $value) ?? $value;
        $value = preg_replace('/\bcase:phys-\d{2}\b/i', '', $value) ?? $value;
        $value = preg_replace('/\bphys-\d{2}\b/i', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
