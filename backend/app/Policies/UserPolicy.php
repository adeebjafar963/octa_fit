<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->isAdmin();
    }
}
