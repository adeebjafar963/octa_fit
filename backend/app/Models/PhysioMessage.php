<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_id',
    'role',
    'content',
])]
class PhysioMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(PhysioAssessment::class, 'assessment_id');
    }
}
