<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagsService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        private readonly FeatureFlagsService $featureFlags,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $key = match ($feature) {
            'coach' => FeatureFlagsService::KEY_COACH,
            'physio' => FeatureFlagsService::KEY_PHYSIO,
            'motion' => FeatureFlagsService::KEY_MOTION,
            default => null,
        };

        if ($key === null) {
            return ApiResponse::error('Unknown feature gate.', 500);
        }

        if (! $this->featureFlags->isEnabled($key)) {
            return ApiResponse::error('This feature is currently disabled.', 403);
        }

        return $next($request);
    }
}
