<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Coach\Semantic\SemanticIntentResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Safe Coach intelligence diagnostics — never exposes API keys or secrets.
 */
class CoachDiagnosticsController extends Controller
{
    public function __construct(
        private readonly SemanticIntentResolver $intentResolver,
    ) {}

    public function __invoke(): JsonResponse
    {
        $diag = $this->intentResolver->diagnostics();

        return ApiResponse::success(
            data: [
                'provider' => $diag['provider'],
                'model' => $diag['model'],
                'semantic_interpreter' => $diag['semantic_interpreter'],
                'openai_enabled' => $diag['openai_enabled_flag'],
                'openai_configured' => $diag['openai_key_present'],
                'fastapi_interpret' => $diag['fastapi_interpret'],
            ],
            message: 'Coach semantic diagnostics',
        );
    }
}
