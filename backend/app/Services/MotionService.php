<?php

namespace App\Services;

use App\Services\Motion\Demo\MotionDemoScenarioService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MotionService
{
    public const EXERCISES = [
        'squat',
        'deadlift',
    ];

    public const MAX_UPLOAD_KB = 20480; // 20 MB

    public function __construct(
        private readonly MotionDemoScenarioService $demoScenarioService = new MotionDemoScenarioService,
    ) {}

    /**
     * @return list<string>
     */
    public static function supportedExercises(): array
    {
        return self::EXERCISES;
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $exercise, UploadedFile $video, ?string $language = null): array
    {
        $exercise = strtolower(trim($exercise));
        if (! in_array($exercise, self::EXERCISES, true)) {
            throw new RuntimeException('Unsupported exercise.');
        }

        $resolvedLanguage = in_array($language, ['en', 'ar'], true) ? $language : 'en';

        // Graduation demo: keep real video upload requirement, return deterministic educational profile.
        $demo = $this->demoScenarioService->tryBuild($exercise, $resolvedLanguage);
        if ($demo !== null) {
            Log::info('motion.demo_profile.hit', [
                'exercise' => $exercise,
                'language' => $resolvedLanguage,
                'video_name' => $video->getClientOriginalName(),
            ]);

            return $demo;
        }

        $baseUrl = rtrim((string) config('services.ai.motion_url'), '/');
        $token = (string) config('services.ai.internal_token');

        $request = Http::timeout(90)
            ->acceptJson()
            ->attach(
                'video',
                file_get_contents($video->getRealPath()),
                $video->getClientOriginalName() ?: 'exercise.mp4',
            );

        if ($token !== '') {
            $request = $request->withHeaders(['X-Internal-Token' => $token]);
        }

        try {
            $response = $request->post($baseUrl.'/analyze', [
                'exercise' => $exercise,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Motion analysis service is temporarily unavailable.');
        }

        if ($response->status() === 422) {
            $detail = $response->json('detail');
            $message = is_string($detail) ? $detail : 'Invalid video or exercise for motion analysis.';
            throw new RuntimeException($message);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Motion analysis service is temporarily unavailable.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Motion analysis returned an invalid response.');
        }

        if (! ($body['success'] ?? false)) {
            $message = is_string($body['message'] ?? null)
                ? $body['message']
                : 'Unable to detect enough body movement from this video.';

            throw new RuntimeException($message);
        }

        // Do not surface fake biomechanical angle metrics in the client payload.
        return [
            'exercise' => (string) ($body['exercise'] ?? $exercise),
            'repetitions' => (int) ($body['repetitions'] ?? 0),
            'duration_seconds' => (float) ($body['duration_seconds'] ?? 0),
            'confidence' => (float) ($body['confidence'] ?? 0),
            'movement_quality' => (string) ($body['movement_quality'] ?? 'unknown'),
            'observations' => array_values(array_filter(
                is_array($body['observations'] ?? null) ? $body['observations'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'tips' => array_values(array_filter(
                is_array($body['tips'] ?? null) ? $body['tips'] : [],
                fn ($v) => is_string($v) && trim($v) !== '',
            )),
            'metrics' => [],
            'provider' => is_string($body['provider'] ?? null) ? $body['provider'] : 'mock',
            'frames_analyzed' => (int) ($body['frames_analyzed'] ?? 0),
            'demo' => false,
            'structured' => false,
            'language' => $resolvedLanguage,
        ];
    }
}
