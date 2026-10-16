<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FriendRequestResource;
use App\Http\Resources\PublicUserResource;
use App\Models\FriendRequest;
use App\Models\User;
use App\Services\FriendshipService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FriendshipController extends Controller
{
    public function __construct(
        private readonly FriendshipService $friendshipService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $paginator = $this->friendshipService->searchUsers(
            $request->user(),
            $q,
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => PublicUserResource::collection($paginator->items())->resolve(),
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

    public function friends(Request $request): JsonResponse
    {
        $paginator = $this->friendshipService->listFriends(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => PublicUserResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'pending_incoming_count' => $this->friendshipService->pendingIncomingCount($request->user()),
            ],
            message: 'Friends retrieved',
        );
    }

    public function incoming(Request $request): JsonResponse
    {
        $paginator = $this->friendshipService->listIncomingRequests(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => FriendRequestResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'pending_incoming_count' => $paginator->total(),
            ],
            message: 'Friend requests retrieved',
        );
    }

    public function showUser(Request $request, int $user): JsonResponse
    {
        $model = $this->friendshipService->findPublicUser($user);
        if ($model === null) {
            return ApiResponse::error('User not found', 404);
        }

        return ApiResponse::success(
            data: (new PublicUserResource($model))->resolve(),
            message: 'User retrieved',
        );
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $receiver = User::query()->findOrFail($validated['user_id']);

        try {
            $friendRequest = $this->friendshipService->sendRequest($request->user(), $receiver);
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to send friend request', 422, $e->errors());
        }

        return ApiResponse::success(
            data: (new FriendRequestResource($friendRequest))->resolve(),
            message: 'Friend request sent',
            status: 201,
        );
    }

    public function accept(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        try {
            $this->friendshipService->acceptRequest($request->user(), $friendRequest);
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to accept friend request', 422, $e->errors());
        }

        $other = $this->friendshipService->findPublicUser((int) $friendRequest->sender_id);

        return ApiResponse::success(
            data: $other !== null ? (new PublicUserResource($other))->resolve() : null,
            message: 'Friend request accepted',
        );
    }

    public function reject(Request $request, FriendRequest $friendRequest): JsonResponse
    {
        try {
            $this->friendshipService->rejectRequest($request->user(), $friendRequest);
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to reject friend request', 422, $e->errors());
        }

        return ApiResponse::success(message: 'Friend request rejected');
    }

    public function remove(Request $request, int $user): JsonResponse
    {
        $friend = User::query()->findOrFail($user);

        try {
            $this->friendshipService->removeFriend($request->user(), $friend);
        } catch (ValidationException $e) {
            return ApiResponse::error('Unable to remove friend', 422, $e->errors());
        }

        return ApiResponse::success(message: 'Friend removed');
    }
}
