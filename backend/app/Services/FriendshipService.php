<?php

namespace App\Services;

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FriendshipService
{
    public const RELATION_NONE = 'none';

    public const RELATION_PENDING_SENT = 'pending_sent';

    public const RELATION_PENDING_RECEIVED = 'pending_received';

    public const RELATION_FRIENDS = 'friends';

    /**
     * @return list<int>
     */
    public function friendIds(User $user): array
    {
        $asUser = Friendship::query()
            ->where('user_id', $user->id)
            ->pluck('friend_id');

        $asFriend = Friendship::query()
            ->where('friend_id', $user->id)
            ->pluck('user_id');

        return $asUser->merge($asFriend)->unique()->values()->all();
    }

    public function areFriends(User $a, User $b): bool
    {
        if ((int) $a->id === (int) $b->id) {
            return false;
        }

        [$low, $high] = $this->orderedPair((int) $a->id, (int) $b->id);

        return Friendship::query()
            ->where('user_id', $low)
            ->where('friend_id', $high)
            ->exists();
    }

    public function relationStatus(User $viewer, User $other): string
    {
        if ((int) $viewer->id === (int) $other->id) {
            return self::RELATION_NONE;
        }

        if ($this->areFriends($viewer, $other)) {
            return self::RELATION_FRIENDS;
        }

        $pending = FriendRequest::query()
            ->where('status', FriendRequest::STATUS_PENDING)
            ->where(function ($q) use ($viewer, $other) {
                $q->where(function ($inner) use ($viewer, $other) {
                    $inner->where('sender_id', $viewer->id)
                        ->where('receiver_id', $other->id);
                })->orWhere(function ($inner) use ($viewer, $other) {
                    $inner->where('sender_id', $other->id)
                        ->where('receiver_id', $viewer->id);
                });
            })
            ->first();

        if ($pending === null) {
            return self::RELATION_NONE;
        }

        if ((int) $pending->sender_id === (int) $viewer->id) {
            return self::RELATION_PENDING_SENT;
        }

        return self::RELATION_PENDING_RECEIVED;
    }

    public function searchUsers(User $viewer, string $query, int $perPage = 20): LengthAwarePaginator
    {
        $query = trim($query);
        $perPage = min(max($perPage, 1), 20);

        if (mb_strlen($query) < 1) {
            return User::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $like = '%'.$query.'%';

        return User::query()
            ->with('profile')
            ->where('is_active', true)
            ->where('id', '!=', $viewer->id)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function listFriends(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);
        $friendIds = $this->friendIds($user);

        return User::query()
            ->with('profile')
            ->whereIn('id', $friendIds)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function listIncomingRequests(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return FriendRequest::query()
            ->with(['sender.profile'])
            ->where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function pendingIncomingCount(User $user): int
    {
        return FriendRequest::query()
            ->where('receiver_id', $user->id)
            ->where('status', FriendRequest::STATUS_PENDING)
            ->count();
    }

    public function sendRequest(User $sender, User $receiver): FriendRequest
    {
        if ((int) $sender->id === (int) $receiver->id) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot send a friend request to yourself.'],
            ]);
        }

        if (! $receiver->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['User not found.'],
            ]);
        }

        if ($this->areFriends($sender, $receiver)) {
            throw ValidationException::withMessages([
                'user_id' => ['You are already friends.'],
            ]);
        }

        $existing = FriendRequest::query()
            ->where(function ($q) use ($sender, $receiver) {
                $q->where(function ($inner) use ($sender, $receiver) {
                    $inner->where('sender_id', $sender->id)
                        ->where('receiver_id', $receiver->id);
                })->orWhere(function ($inner) use ($sender, $receiver) {
                    $inner->where('sender_id', $receiver->id)
                        ->where('receiver_id', $sender->id);
                });
            })
            ->where('status', FriendRequest::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'user_id' => ['A friend request already exists.'],
            ]);
        }

        return FriendRequest::query()->updateOrCreate(
            [
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
            ],
            [
                'status' => FriendRequest::STATUS_PENDING,
            ],
        )->load(['receiver.profile', 'sender.profile']);
    }

    public function acceptRequest(User $receiver, FriendRequest $request): Friendship
    {
        if ((int) $request->receiver_id !== (int) $receiver->id) {
            abort(403, 'Not allowed to accept this request.');
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['This friend request is no longer pending.'],
            ]);
        }

        return DB::transaction(function () use ($request) {
            $request->status = FriendRequest::STATUS_ACCEPTED;
            $request->save();

            FriendRequest::query()
                ->where('status', FriendRequest::STATUS_PENDING)
                ->where(function ($q) use ($request) {
                    $q->where(function ($inner) use ($request) {
                        $inner->where('sender_id', $request->receiver_id)
                            ->where('receiver_id', $request->sender_id);
                    });
                })
                ->update(['status' => FriendRequest::STATUS_CANCELLED]);

            return $this->createFriendship((int) $request->sender_id, (int) $request->receiver_id);
        });
    }

    public function rejectRequest(User $receiver, FriendRequest $request): void
    {
        if ((int) $request->receiver_id !== (int) $receiver->id) {
            abort(403, 'Not allowed to reject this request.');
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['This friend request is no longer pending.'],
            ]);
        }

        $request->status = FriendRequest::STATUS_REJECTED;
        $request->save();
    }

    public function cancelRequest(User $sender, FriendRequest $request): void
    {
        if ((int) $request->sender_id !== (int) $sender->id) {
            abort(403, 'Not allowed to cancel this request.');
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'request' => ['This friend request is no longer pending.'],
            ]);
        }

        $request->status = FriendRequest::STATUS_CANCELLED;
        $request->save();
    }

    public function removeFriend(User $user, User $friend): void
    {
        if (! $this->areFriends($user, $friend)) {
            throw ValidationException::withMessages([
                'user_id' => ['You are not friends with this user.'],
            ]);
        }

        [$low, $high] = $this->orderedPair((int) $user->id, (int) $friend->id);

        Friendship::query()
            ->where('user_id', $low)
            ->where('friend_id', $high)
            ->delete();
    }

    public function findPublicUser(int $userId): ?User
    {
        return User::query()
            ->with('profile')
            ->where('id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return Collection<int, int>
     */
    public function visibleAuthorIds(User $viewer): Collection
    {
        return collect($this->friendIds($viewer))->push($viewer->id)->unique()->values();
    }

    private function createFriendship(int $a, int $b): Friendship
    {
        [$low, $high] = $this->orderedPair($a, $b);

        return Friendship::query()->firstOrCreate([
            'user_id' => $low,
            'friend_id' => $high,
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function orderedPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
