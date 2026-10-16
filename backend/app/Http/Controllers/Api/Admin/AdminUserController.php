<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->userService->list(
            $request->query('search'),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => UserResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Users retrieved',
        );
    }

    public function show(User $user): JsonResponse
    {
        $model = $this->userService->find($user->id);
        if ($model === null) {
            return ApiResponse::error('User not found', 404);
        }

        return ApiResponse::success(
            data: (new UserResource($model))->resolve(),
            message: 'User retrieved',
        );
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        try {
            $updated = $this->userService->updateRole($user, $request->validated('role'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            data: (new UserResource($updated))->resolve(),
            message: 'User role updated',
        );
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        try {
            $updated = $this->userService->updateStatus($user, (bool) $request->validated('is_active'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            data: (new UserResource($updated))->resolve(),
            message: 'User status updated',
        );
    }
}
