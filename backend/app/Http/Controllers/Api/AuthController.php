<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            message: 'Registration successful',
            status: 201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                message: 'Invalid credentials',
                status: 401,
                errors: $e->errors(),
            );
        }

        return ApiResponse::success(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            message: 'Login successful',
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['role', 'profile']);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
            ],
            message: 'Authenticated user',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(
            message: 'Logged out successfully',
        );
    }
}
