<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class AdminUserService
{
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 50);

        return User::query()
            ->with('role')
            ->when($search, function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?User
    {
        return User::query()->with(['role', 'profile'])->find($id);
    }

    public function updateRole(User $target, string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            throw new RuntimeException('Invalid role.');
        }

        $wasAdmin = $target->isAdmin();
        $willBeAdmin = $role->name === 'admin';

        if ($wasAdmin && ! $willBeAdmin) {
            $adminCount = User::query()
                ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                ->count();
            if ($adminCount <= 1) {
                throw new RuntimeException('Cannot remove the last remaining administrator.');
            }
        }

        $target->role_id = $role->id;
        $target->save();

        return $target->fresh(['role', 'profile']);
    }

    public function updateStatus(User $target, bool $isActive): User
    {
        if ($target->isAdmin() && ! $isActive) {
            $adminCount = User::query()
                ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                ->where('is_active', true)
                ->count();
            if ($adminCount <= 1) {
                throw new RuntimeException('Cannot deactivate the last remaining active administrator.');
            }
        }

        $target->is_active = $isActive;
        $target->save();

        return $target->fresh(['role', 'profile']);
    }
}
