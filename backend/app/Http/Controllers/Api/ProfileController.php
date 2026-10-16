<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ProfileService $profileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['role', 'profile']);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
            ],
            message: 'Profile retrieved',
        );
    }

    public function completion(Request $request): JsonResponse
    {
        $completion = $this->profileService->completion($request->user());

        return ApiResponse::success(
            data: $completion,
            message: 'Profile completion status',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->update($request->user(), $request->validated());

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
            ],
            message: 'Profile updated',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $token = $this->authService->changePassword(
                $user,
                $request->validated('current_password'),
                $request->validated('password'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(
                message: 'Password change failed',
                status: 422,
                errors: $e->errors(),
            );
        }

        $user->load(['role', 'profile']);

        return ApiResponse::success(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            message: 'Password changed successfully',
        );
    }
}
