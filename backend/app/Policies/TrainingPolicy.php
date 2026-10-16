<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\TrainingCategory;
use App\Models\User;

/**
 * Authorization foundation for future admin training management.
 * Normal users may only view; only admins may mutate.
 */
class TrainingPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, TrainingCategory|Exercise $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
