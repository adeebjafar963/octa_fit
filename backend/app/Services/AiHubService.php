<?php

namespace App\Services;

use App\Models\CoachConversation;
use App\Models\PhysioAssessment;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiHubService
{
    public function __construct(
        private readonly FeatureFlagsService $featureFlags,
        private readonly AiRouterService $router,
        private readonly ProfileCompletionService $completionService,
        private readonly UserAiContextBuilder $aiContextBuilder,
    ) {}

    /**
     * @return array{
     *   capabilities: array{coach: bool, physio: bool, motion: bool},
     *   greeting: array<string, mixed>,
     *   recent: array{conversations: list<array<string, mixed>>, assessments: list<array<string, mixed>>},
     *   recommended_action: array{action: string, capability: string, label_key: string}|null
     * }
     */
    public function hub(User $user): array
    {
        $user->loadMissing('profile');
        $flags = $this->featureFlags->publicFlags();
        $capabilities = [
            'coach' => $flags['coach_enabled'],
            'physio' => $flags['physio_enabled'],
            'motion' => $flags['motion_enabled'],
        ];

        $profile = $user->profile;
        $greeting = [
            'name' => $user->name,
            'training_goal' => $profile?->training_goal,
            'fitness_level' => $profile?->fitness_level,
            'profile_complete' => $profile !== null
                ? $this->completionService->isComplete($profile)
                : false,
        ];

        $conversations = [];
        if ($capabilities['coach']) {
            $conversations = CoachConversation::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'title', 'updated_at', 'created_at'])
                ->map(fn (CoachConversation $c) => [
                    'id' => $c->id,
                    'title' => $c->title,
                    'updated_at' => optional($c->updated_at)?->toIso8601String(),
                ])
                ->all();
        }

        $assessments = [];
        if ($capabilities['physio']) {
            $assessments = PhysioAssessment::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'body_area', 'risk_level', 'requires_urgent_care', 'updated_at', 'created_at'])
                ->map(fn (PhysioAssessment $a) => [
                    'id' => $a->id,
                    'body_area' => $a->body_area,
                    'risk_level' => $a->risk_level,
                    'requires_urgent_care' => (bool) $a->requires_urgent_care,
                    'updated_at' => optional($a->updated_at)?->toIso8601String(),
                ])
                ->all();
        }

        return [
            'capabilities' => $capabilities,
            'greeting' => $greeting,
            'recent' => [
                'conversations' => $conversations,
                'assessments' => $assessments,
            ],
            'recommended_action' => $this->recommendedAction($capabilities, $greeting),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function route(User $user, string $message, ?string $language = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new HttpException(422, 'Message is required.');
        }

        $decision = $this->router->route($message);
        $feature = $decision['requires_feature'];
        $enabled = match ($feature) {
            'coach' => $this->featureFlags->isEnabled(FeatureFlagsService::KEY_COACH),
            'physio' => $this->featureFlags->isEnabled(FeatureFlagsService::KEY_PHYSIO),
            'motion' => $this->featureFlags->isEnabled(FeatureFlagsService::KEY_MOTION),
            default => false,
        };

        if (! $enabled) {
            throw new HttpException(403, 'This feature is currently disabled.');
        }

        $user->loadMissing('profile');
        // Context is built server-side for audit/debug — never trust client profile claims.
        $context = match ($feature) {
            'physio' => $this->aiContextBuilder->forPhysio($user),
            default => $this->aiContextBuilder->forCoach($user),
        };

        return [
            ...$decision,
            'enabled' => true,
            'language' => $language === 'ar' ? 'ar' : 'en',
            'message' => $message,
            'profile_available' => (bool) ($context['available'] ?? false),
            'navigation' => $this->navigationFor($decision['action']),
        ];
    }

    /**
     * @param  array{coach: bool, physio: bool, motion: bool}  $capabilities
     * @param  array<string, mixed>  $greeting
     * @return array{action: string, capability: string, label_key: string}|null
     */
    private function recommendedAction(array $capabilities, array $greeting): ?array
    {
        if ($capabilities['coach']) {
            return [
                'action' => AiRouterService::ACTION_OPEN_COACH_CHAT,
                'capability' => 'coach',
                'label_key' => ($greeting['training_goal'] ?? null) !== null
                    ? 'ai_hub_rec_train'
                    : 'ai_hub_rec_ask',
            ];
        }
        if ($capabilities['physio']) {
            return [
                'action' => AiRouterService::ACTION_OPEN_PHYSIO_NEW,
                'capability' => 'physio',
                'label_key' => 'ai_hub_rec_physio',
            ];
        }
        if ($capabilities['motion']) {
            return [
                'action' => AiRouterService::ACTION_OPEN_MOTION,
                'capability' => 'motion',
                'label_key' => 'ai_hub_rec_motion',
            ];
        }

        return null;
    }

    /**
     * @return array{route: string, creates_conversation: bool}
     */
    private function navigationFor(string $action): array
    {
        return match ($action) {
            AiRouterService::ACTION_OPEN_PHYSIO_NEW => [
                'route' => '/physio/new',
                'creates_conversation' => false,
            ],
            AiRouterService::ACTION_OPEN_MOTION => [
                'route' => '/motion',
                'creates_conversation' => false,
            ],
            AiRouterService::ACTION_OPEN_COACH => [
                'route' => '/coach',
                'creates_conversation' => false,
            ],
            default => [
                'route' => '/coach/chat',
                'creates_conversation' => true,
            ],
        };
    }
}
