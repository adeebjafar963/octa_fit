<?php

namespace App\Services\Coach\Semantic;

/**
 * Stable, extensible semantic intent ontology for the Coach NLU layer.
 * Internal only — never exposed as application commands without allowlist validation.
 */
final class IntentOntology
{
    public const DOMAINS = [
        'training',
        'nutrition',
        'sports',
        'medical',
        'system',
        'conversation',
        'safety',
        'sports_knowledge',
    ];

    /** @var list<string> */
    public const INTENTS = [
        // Nutrition
        'nutrition.lookup',
        'nutrition.quantity_lookup',
        'nutrition.compare',
        'nutrition.substitution',
        'nutrition.meal',
        'nutrition.meal_analysis',
        'nutrition.calorie_question',
        'nutrition.protein_question',
        'nutrition.macro_question',
        'nutrition.nutrition_general_knowledge',
        'nutrition.plan_create',
        'nutrition.plan_modify',
        'nutrition.plan_replace_food',
        'nutrition.plan_adjust_calories',
        'nutrition.plan_adjust_macros',
        'nutrition.allergy_constraint',
        'nutrition.preference_constraint',
        // Training
        'training.general_question',
        'training.exercise_question',
        'training.exercise_recommendation',
        'training.body_part_request',
        'training.workout_create',
        'training.workout_modify',
        'training.workout_replace_exercise',
        'training.workout_change_day',
        'training.workout_change_duration',
        'training.workout_change_difficulty',
        'training.workout_change_equipment',
        'training.workout_change_days',
        'training.workout_change_goal',
        'training.progression_question',
        'training.recovery_question',
        'training.warmup_question',
        'training.rest_question',
        // Discipline (sub-facets — also stored as entities)
        'training.bodybuilding',
        'training.mma',
        'training.home',
        'training.calisthenics',
        // Plan
        'plan.create',
        'plan.modify',
        'plan.resume',
        'plan.replace',
        'plan.adjust',
        // General knowledge
        'sports.general',
        'fitness.general',
        'nutrition.general',
        // Safety
        'medical.safety',
        'injury.safety',
        'emergency.safety',
        'unsafe_training_request',
        // System
        'prompt_injection',
        'unsupported_request',
        'ambiguous_request',
        // Legacy bridge intents (still valid internally)
        'nutrition_lookup',
        'nutrition_plan_create',
        'nutrition_plan_modify',
        'training_plan_create',
        'training_plan_modify',
        'training_exercise_recommendation',
        'general_sports_knowledge',
        'general_conversation',
        'safety',
        'memory_related',
        'exercise_lookup',
        'training_plan_resume',
        'clarification',
    ];

    /**
     * Map ontology / provider intent → canonical internal intent used by engines.
     */
    public static function canonicalize(string $intent): string
    {
        return match ($intent) {
            'nutrition.lookup',
            'nutrition.quantity_lookup',
            'nutrition.calorie_question',
            'nutrition.protein_question',
            'nutrition.macro_question',
            'nutrition.compare',
            'nutrition.meal',
            'nutrition.meal_analysis' => 'nutrition_lookup',
            'nutrition.plan_create',
            'nutrition.allergy_constraint',
            'nutrition.preference_constraint' => 'nutrition_plan_create',
            'nutrition.plan_modify',
            'nutrition.plan_replace_food',
            'nutrition.plan_adjust_calories',
            'nutrition.plan_adjust_macros',
            'nutrition_plan_modify' => 'nutrition_plan_modify',
            // Substitution advice is knowledge/RAG, not a DB lookup inventing foods.
            'nutrition.substitution' => 'general_sports_knowledge',
            'nutrition.nutrition_general_knowledge',
            'nutrition.general' => 'general_sports_knowledge',
            'training.workout_create',
            'plan.create',
            'training.bodybuilding',
            'training.mma',
            'training.home',
            'training.calisthenics' => 'training_plan_create',
            'training.workout_modify',
            'training.workout_replace_exercise',
            'training.workout_change_day',
            'training.workout_change_duration',
            'training.workout_change_difficulty',
            'training.workout_change_equipment',
            'training.workout_change_days',
            'training.workout_change_goal',
            'plan.modify',
            'plan.replace',
            'plan.adjust' => 'training_plan_modify',
            'plan.resume' => 'training_plan_resume',
            'training.exercise_recommendation',
            'training.body_part_request',
            'training.exercise_question' => 'training_exercise_recommendation',
            'training.general_question',
            'training.progression_question',
            'training.recovery_question',
            'training.warmup_question',
            'training.rest_question',
            'sports.general',
            'fitness.general' => 'general_sports_knowledge',
            'medical.safety',
            'injury.safety',
            'emergency.safety',
            'unsafe_training_request' => 'safety',
            'ambiguous_request', 'clarification' => 'ambiguous_request',
            'prompt_injection',
            'unsupported_request' => 'unsupported_request',
            'memory_related', 'memory.allergy', 'memory.preference' => 'memory_related',
            'exercise_lookup', 'exercise.lookup' => 'exercise_lookup',
            default => $intent,
        };
    }

    public static function domainFor(string $canonicalIntent): string
    {
        return match ($canonicalIntent) {
            'nutrition_lookup', 'nutrition_plan_create', 'nutrition_plan_modify' => 'nutrition',
            'training_plan_create',
            'training_plan_modify',
            'training_plan_resume',
            'training_exercise_recommendation',
            'exercise_lookup' => 'training',
            'general_sports_knowledge' => 'sports_knowledge',
            'safety' => 'safety',
            'ambiguous_request', 'unsupported_request', 'memory_related' => 'system',
            default => 'conversation',
        };
    }

    public static function isAllowed(string $intent): bool
    {
        return in_array($intent, self::INTENTS, true)
            || in_array(self::canonicalize($intent), self::INTENTS, true);
    }

    /**
     * Topic bucket used by TopicSwitchDetector / ConversationTopicState.
     */
    public static function topicBucket(string $canonicalIntent, string $domain): string
    {
        if ($domain === 'safety' || $canonicalIntent === 'safety') {
            return 'safety';
        }
        if (str_starts_with($canonicalIntent, 'nutrition')
            || $canonicalIntent === 'nutrition_plan_modify'
            || $domain === 'nutrition') {
            return 'nutrition';
        }
        if (in_array($canonicalIntent, [
            'training_plan_create',
            'training_plan_modify',
            'training_plan_resume',
            'training_exercise_recommendation',
        ], true) || $domain === 'training') {
            return 'training';
        }
        if ($canonicalIntent === 'general_sports_knowledge' || $domain === 'sports_knowledge') {
            return 'knowledge';
        }

        return 'general';
    }
}
