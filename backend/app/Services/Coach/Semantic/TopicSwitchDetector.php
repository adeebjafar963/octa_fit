<?php

namespace App\Services\Coach\Semantic;

/**
 * Compares CURRENT message topic against active/recent topics.
 * Never lets old topic dominate a clear current-message switch.
 */
class TopicSwitchDetector
{
    public const SAME_TOPIC = 'same_topic';

    public const FOLLOW_UP = 'follow_up';

    public const TOPIC_SWITCH = 'topic_switch';

    public const TOPIC_RESUME = 'topic_resume';

    public const AMBIGUOUS = 'ambiguous';

    /**
     * @param  list<string>  $recentTopics  newest-last topic buckets
     * @return array{
     *   decision: string,
     *   current_topic: string,
     *   previous_topic: string|null,
     *   is_follow_up: bool,
     *   is_topic_switch: bool,
     *   resume_topic: string|null
     * }
     */
    public function detect(
        SemanticIntentContract $current,
        ?string $activeTopic,
        array $recentTopics = [],
        bool $semanticSaysFollowUp = false,
        bool $explicitPlanReference = false,
    ): array {
        $currentTopic = IntentOntology::topicBucket($current->intent, $current->domain);
        $previous = $activeTopic;

        // Explicit resume ("رجعني للخطة", "back to the plan")
        if ($current->intent === 'training_plan_resume' || $this->isResumePhrase($current)) {
            $resume = $this->findPriorTopic($recentTopics, ['training', 'nutrition'], $currentTopic);

            return [
                'decision' => self::TOPIC_RESUME,
                'current_topic' => $resume ?? $currentTopic,
                'previous_topic' => $previous,
                'is_follow_up' => true,
                'is_topic_switch' => false,
                'resume_topic' => $resume,
            ];
        }

        if ($semanticSaysFollowUp || $current->intent === 'training_plan_modify' || $current->needsActivePlan()) {
            return [
                'decision' => self::FOLLOW_UP,
                'current_topic' => $previous ?? $currentTopic,
                'previous_topic' => $previous,
                'is_follow_up' => true,
                'is_topic_switch' => false,
                'resume_topic' => null,
            ];
        }

        if ($explicitPlanReference && $previous !== null) {
            return [
                'decision' => self::FOLLOW_UP,
                'current_topic' => $previous,
                'previous_topic' => $previous,
                'is_follow_up' => true,
                'is_topic_switch' => false,
                'resume_topic' => null,
            ];
        }

        if ($previous === null || $previous === $currentTopic) {
            return [
                'decision' => self::SAME_TOPIC,
                'current_topic' => $currentTopic,
                'previous_topic' => $previous,
                'is_follow_up' => false,
                'is_topic_switch' => false,
                'resume_topic' => null,
            ];
        }

        // Different bucket → topic switch (current message wins).
        if ($this->isHardSwitch($previous, $currentTopic)) {
            return [
                'decision' => self::TOPIC_SWITCH,
                'current_topic' => $currentTopic,
                'previous_topic' => $previous,
                'is_follow_up' => false,
                'is_topic_switch' => true,
                'resume_topic' => null,
            ];
        }

        if ($current->confidence < 0.55 && $currentTopic === 'general') {
            return [
                'decision' => self::AMBIGUOUS,
                'current_topic' => $currentTopic,
                'previous_topic' => $previous,
                'is_follow_up' => false,
                'is_topic_switch' => false,
                'resume_topic' => null,
            ];
        }

        return [
            'decision' => self::TOPIC_SWITCH,
            'current_topic' => $currentTopic,
            'previous_topic' => $previous,
            'is_follow_up' => false,
            'is_topic_switch' => true,
            'resume_topic' => null,
        ];
    }

    private function isHardSwitch(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }
        $pairs = [
            'nutrition' => ['training', 'knowledge'],
            'training' => ['nutrition', 'knowledge'],
            'knowledge' => ['training', 'nutrition'],
        ];

        return in_array($to, $pairs[$from] ?? [], true) || ($from !== $to && $to !== 'general');
    }

    /**
     * @param  list<string>  $recent
     * @param  list<string>  $wanted
     */
    private function findPriorTopic(array $recent, array $wanted, string $exclude): ?string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $t = $recent[$i];
            if ($t !== $exclude && in_array($t, $wanted, true)) {
                return $t;
            }
        }

        return null;
    }

    private function isResumePhrase(SemanticIntentContract $current): bool
    {
        // Intent may already be resume; entities may carry resume flag from interpreter.
        return ($current->entities['resume_plan'] ?? false) === true
            || ($current->subIntent === 'resume');
    }
}
