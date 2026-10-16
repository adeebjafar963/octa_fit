<?php

namespace App\Policies;

use App\Models\CoachConversation;
use App\Models\User;

class CoachConversationPolicy
{
    public function view(User $user, CoachConversation $conversation): bool
    {
        return (int) $user->id === (int) $conversation->user_id;
    }

    public function update(User $user, CoachConversation $conversation): bool
    {
        return (int) $user->id === (int) $conversation->user_id;
    }

    public function delete(User $user, CoachConversation $conversation): bool
    {
        return (int) $user->id === (int) $conversation->user_id;
    }
}
