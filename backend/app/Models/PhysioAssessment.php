<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'body_area',
    'symptoms',
    'pain_level',
    'duration',
    'onset',
    'trigger',
    'activity',
    'additional_information',
    'risk_level',
    'requires_urgent_care',
    'result_payload',
])]
class PhysioAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'pain_level' => 'integer',
            'requires_urgent_care' => 'boolean',
            'result_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PhysioMessage::class, 'assessment_id');
    }
}
