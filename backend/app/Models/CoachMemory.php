<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'category',
    'key',
    'value',
    'source',
    'confidence',
    'explicit',
    'active',
    'first_seen_at',
    'last_confirmed_at',
    'expires_at',
    'metadata',
])]
class CoachMemory extends Model
{
    public const CATEGORY_PROFILE = 'profile_preference';

    public const CATEGORY_TRAINING = 'training_preference';

    public const CATEGORY_NUTRITION = 'nutrition_preference';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_GOAL = 'goal';

    public const CATEGORY_SCHEDULE = 'schedule';

    public const CATEGORY_FOOD = 'food_preference';

    public const CATEGORY_COACHING = 'coaching_preference';

    public const SOURCE_EXPLICIT = 'explicit';

    public const SOURCE_INFERRED = 'inferred';

    public const CATEGORIES = [
        self::CATEGORY_PROFILE,
        self::CATEGORY_TRAINING,
        self::CATEGORY_NUTRITION,
        self::CATEGORY_EQUIPMENT,
        self::CATEGORY_GOAL,
        self::CATEGORY_SCHEDULE,
        self::CATEGORY_FOOD,
        self::CATEGORY_COACHING,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'metadata' => 'array',
            'confidence' => 'float',
            'explicit' => 'boolean',
            'active' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $id = $user instanceof User ? $user->id : $user;

        return $query->where('user_id', $id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Compact public payload for AI context (no internal ids).
     *
     * @return array<string, mixed>
     */
    public function toContextItem(): array
    {
        return [
            'category' => $this->category,
            'key' => $this->key,
            'value' => $this->value,
            'source' => $this->source,
            'confidence' => $this->confidence,
            'last_confirmed_at' => $this->last_confirmed_at?->toIso8601String(),
            'active' => $this->active && ! $this->isExpired(),
        ];
    }

    /**
     * Safe display payload for Flutter (no raw DB internals beyond id for deactivate).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'key' => $this->key,
            'value' => $this->value,
            'source' => $this->source,
            'explicit' => $this->explicit,
            'confidence' => $this->confidence,
            'active' => $this->active && ! $this->isExpired(),
            'last_confirmed_at' => $this->last_confirmed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
