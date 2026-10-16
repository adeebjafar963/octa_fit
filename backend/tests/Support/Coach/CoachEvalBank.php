<?php

namespace Tests\Support\Coach;

/**
 * Loads the Master Intelligence Evaluation Bank (evaluation dataset only).
 */
final class CoachEvalBank
{
    private static ?array $cache = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function cases(): array
    {
        return self::payload()['cases'];
    }

    /**
     * @return array{meta: array<string, mixed>, cases: list<array<string, mixed>>}
     */
    public static function payload(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = dirname(__DIR__, 2).'/Fixtures/Coach/master_eval_bank.json';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Eval bank missing: {$path}");
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! isset($data['cases']) || ! is_array($data['cases'])) {
            throw new \RuntimeException('Eval bank missing cases array');
        }

        return self::$cache = $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function casesWithLevel(string $level): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (array $c) => in_array($level, $c['levels'] ?? ['intent'], true),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function casesInCategories(array $categories): array
    {
        $set = array_flip($categories);

        return array_values(array_filter(
            self::cases(),
            static fn (array $c) => isset($set[$c['category'] ?? '']),
        ));
    }

    /**
     * Critical demonstration / live-smoke cases that must hard-pass.
     *
     * @return list<array<string, mixed>>
     */
    public static function criticalCases(): array
    {
        $ids = [
            'TRAIN_MMA_001', 'TRAIN_MMA_005', 'TRAIN_GYM_001',
            'NUT_LOOKUP_001', 'NUT_LOOKUP_004', 'QA_009', 'SAFETY_001', 'SAFETY_008',
            'DEMO_001', 'DEMO_002', 'DEMO_004', 'FOLLOW_002', 'FOLLOW_004',
            'COMPOUND_001', 'EN_003', 'MIX_001', 'NOVEL_001', 'NOVEL_002',
        ];
        $byId = [];
        foreach (self::cases() as $c) {
            $byId[$c['id']] = $c;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        // Fallback: first MMA/gym/nutrition demo-like messages if IDs differ.
        if (count($out) < 5) {
            foreach (self::cases() as $c) {
                if (in_array($c['category'] ?? '', ['mma', 'gym_plan', 'nutrition_lookup', 'demonstration', 'novel_paraphrase'], true)) {
                    $out[] = $c;
                }
                if (count($out) >= 20) {
                    break;
                }
            }
        }

        return $out;
    }
}
