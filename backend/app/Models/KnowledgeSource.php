<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'organization',
    'source_type',
    'evidence_level',
    'url',
    'version',
    'publication_year',
    'publication_date',
    'reliability_level',
    'language',
    'is_active',
    'metadata',
])]
class KnowledgeSource extends Model
{
    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'publication_year' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
