<?php

namespace App\Services;

/**
 * Authoritative AI intent router — safety first, then specialized capabilities.
 * Keyword scoring is bilingual (EN/AR) and intentionally conservative.
 */
class AiRouterService
{
    public const INTENT_SAFETY = 'safety';

    public const INTENT_PHYSIO = 'physio';

    public const INTENT_MOTION = 'motion';

    public const INTENT_COACH = 'coach';

    public const INTENT_GENERAL = 'general';

    public const ACTION_OPEN_PHYSIO_NEW = 'open_physio_new';

    public const ACTION_OPEN_MOTION = 'open_motion';

    public const ACTION_OPEN_COACH_CHAT = 'open_coach_chat';

    public const ACTION_OPEN_COACH = 'open_coach';

    /**
     * @return array{
     *   intent: string,
     *   capability: string,
     *   action: string,
     *   priority: string,
     *   requires_feature: string,
     *   confidence: float,
     *   reasons: list<string>
     * }
     */
    public function route(string $message): array
    {
        $text = mb_strtolower(trim($message));
        $reasons = [];

        $safety = $this->score($text, $this->safetyPatterns());
        $physio = $this->score($text, $this->physioPatterns());
        $motion = $this->score($text, $this->motionPatterns());
        $nutrition = $this->score($text, $this->nutritionPatterns());
        $hydration = $this->score($text, $this->hydrationPatterns());
        $training = $this->score($text, $this->trainingPatterns());

        // 1) Emergency / safety always wins.
        if ($safety >= 2) {
            $reasons[] = 'safety_keywords';

            return $this->result(
                intent: self::INTENT_SAFETY,
                capability: 'physio',
                action: self::ACTION_OPEN_PHYSIO_NEW,
                priority: 'safety',
                feature: 'physio',
                confidence: min(1.0, 0.55 + ($safety * 0.1)),
                reasons: $reasons,
            );
        }

        // 2) Medical / injury → physio
        if ($physio >= 2 || ($physio >= 1 && $safety >= 1)) {
            $reasons[] = 'injury_or_pain_keywords';

            return $this->result(
                intent: self::INTENT_PHYSIO,
                capability: 'physio',
                action: self::ACTION_OPEN_PHYSIO_NEW,
                priority: 'medical',
                feature: 'physio',
                confidence: min(1.0, 0.5 + ($physio * 0.12)),
                reasons: $reasons,
            );
        }

        // 3) Motion / form analysis
        if ($motion >= 2) {
            $reasons[] = 'motion_form_keywords';

            return $this->result(
                intent: self::INTENT_MOTION,
                capability: 'motion',
                action: self::ACTION_OPEN_MOTION,
                priority: 'motion',
                feature: 'motion',
                confidence: min(1.0, 0.5 + ($motion * 0.12)),
                reasons: $reasons,
            );
        }

        // 4) Training / nutrition / hydration → coach
        if ($training >= 1 || $nutrition >= 1 || $hydration >= 1 || $motion === 1) {
            $priority = match (true) {
                $hydration > $training && $hydration > $nutrition => 'hydration',
                $nutrition > $training => 'nutrition',
                default => 'training',
            };
            $reasons[] = $priority.'_keywords';

            return $this->result(
                intent: self::INTENT_COACH,
                capability: 'coach',
                action: self::ACTION_OPEN_COACH_CHAT,
                priority: $priority,
                feature: 'coach',
                confidence: min(1.0, 0.45 + (max($training, $nutrition, $hydration) * 0.12)),
                reasons: $reasons,
            );
        }

        // 5) General → coach chat (default fitness assistant)
        $reasons[] = 'default_general';

        return $this->result(
            intent: self::INTENT_GENERAL,
            capability: 'coach',
            action: self::ACTION_OPEN_COACH_CHAT,
            priority: 'general',
            feature: 'coach',
            confidence: 0.35,
            reasons: $reasons,
        );
    }

    /**
     * @param  list<string>  $reasons
     * @return array{
     *   intent: string,
     *   capability: string,
     *   action: string,
     *   priority: string,
     *   requires_feature: string,
     *   confidence: float,
     *   reasons: list<string>
     * }
     */
    private function result(
        string $intent,
        string $capability,
        string $action,
        string $priority,
        string $feature,
        float $confidence,
        array $reasons,
    ): array {
        return [
            'intent' => $intent,
            'capability' => $capability,
            'action' => $action,
            'priority' => $priority,
            'requires_feature' => $feature,
            'confidence' => round($confidence, 2),
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private function score(string $text, array $patterns): int
    {
        $score = 0;
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && mb_strpos($text, $pattern) !== false) {
                $score++;
            }
        }

        return $score;
    }

    /** @return list<string> */
    private function safetyPatterns(): array
    {
        return [
            'chest pain', 'heart attack', 'can\'t breathe', 'cannot breathe',
            'difficulty breathing', 'stroke', 'unconscious', 'severe chest',
            'ألم في الصدر', 'الم في الصدر', 'ألم صدر', 'نوبة قلبية', 'صعوبة في التنفس',
            'لا أستطيع التنفس', 'لا استطيع التنفس', 'سكتة', 'إغماء', 'اغماء',
            'bleeding heavily', 'نزيف شديد', 'طارئ', 'emergency',
        ];
    }

    /** @return list<string> */
    private function physioPatterns(): array
    {
        return [
            'pain', 'hurt', 'hurts', 'injury', 'injured', 'sore', 'sprain', 'strain',
            'knee', 'shoulder', 'back pain', 'lower back', 'rehab', 'recovery',
            'something hurts', 'swollen', 'inflammation',
            'ألم', 'الم', 'يؤلمني', 'تؤلمني', 'توجعني', 'اصابة', 'إصابة', 'ملتوي', 'تورم',
            'ركبة', 'ركبتي', 'كتف', 'كتفي', 'ظهر', 'تعافي', 'علاج طبيعي', 'فيزيو',
        ];
    }

    /** @return list<string> */
    private function motionPatterns(): array
    {
        return [
            'form', 'posture', 'technique', 'analyze my', 'analyse my', 'video analysis',
            'motion', 'squat form', 'deadlift form', 'am i doing',
            'وضعيتي', 'وضعية', 'تقنيتي', 'حلل', 'تحليل الحركة', 'فورم', 'شكل التمرين',
            'هل وضعيتي', 'تحليل فيديو',
        ];
    }

    /** @return list<string> */
    private function nutritionPatterns(): array
    {
        return [
            'nutrition', 'protein', 'calories', 'diet', 'meal', 'macros', 'carb',
            'تغذية', 'بروتين', 'سعرات', 'نظام غذائي', 'وجبة', 'وجبات',
        ];
    }

    /** @return list<string> */
    private function hydrationPatterns(): array
    {
        return [
            'hydration', 'water intake', 'how much water', 'drink water', 'dehydrated',
            'ترطيب', 'كمية الماء', 'شرب الماء', 'ماء اليوم', 'جفاف',
        ];
    }

    /** @return list<string> */
    private function trainingPatterns(): array
    {
        return [
            'workout', 'training', 'train', 'exercise', 'plan', 'program',
            'muscle', 'strength', 'hypertrophy', 'today\'s workout', 'todays workout',
            'تمرين', 'تدريب', 'خطة', 'برنامج', 'عضلات', 'بناء العضلات', 'تمرين اليوم',
            'قوة', 'لياقة',
        ];
    }
}
