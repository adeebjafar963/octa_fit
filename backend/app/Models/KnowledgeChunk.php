<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'knowledge_document_id',
    'chunk_index',
    'content',
    'topic',
    'token_estimate',
    'content_hash',
    'is_active',
    'metadata',
])]
class KnowledgeChunk extends Model
{
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_estimate' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
