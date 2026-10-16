<?php

namespace App\Policies;

use App\Models\CoachMemory;
use App\Models\User;

class CoachMemoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CoachMemory $memory): bool
    {
        return (int) $user->id === (int) $memory->user_id;
    }

    public function update(User $user, CoachMemory $memory): bool
    {
        return (int) $user->id === (int) $memory->user_id;
    }

    public function delete(User $user, CoachMemory $memory): bool
    {
        return (int) $user->id === (int) $memory->user_id;
    }
}
