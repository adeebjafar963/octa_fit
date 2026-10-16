<?php

namespace App\Policies;

use App\Models\PhysioAssessment;
use App\Models\User;

class PhysioAssessmentPolicy
{
    public function view(User $user, PhysioAssessment $assessment): bool
    {
        return (int) $user->id === (int) $assessment->user_id;
    }

    public function update(User $user, PhysioAssessment $assessment): bool
    {
        return (int) $user->id === (int) $assessment->user_id;
    }

    public function delete(User $user, PhysioAssessment $assessment): bool
    {
        return (int) $user->id === (int) $assessment->user_id;
    }
}
