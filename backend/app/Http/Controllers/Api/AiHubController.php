<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\RouteAiRequest;
use App\Services\AiHubService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiHubController extends Controller
{
    public function __construct(
        private readonly AiHubService $aiHub,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return ApiResponse::success(
            data: $this->aiHub->hub($user),
            message: 'AI hub loaded',
        );
    }

    public function route(RouteAiRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        try {
            $data = $this->aiHub->route(
                $user,
                (string) $request->validated('message'),
                $request->validated('language'),
            );
        } catch (HttpException $e) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->getStatusCode(),
            );
        }

        return ApiResponse::success(
            data: $data,
            message: 'AI route resolved',
        );
    }
}
