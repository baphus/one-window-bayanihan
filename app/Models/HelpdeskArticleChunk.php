<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskArticleChunk extends Model
{
    use HasFactory, UsesUuid;

    protected $table = 'helpdesk_article_chunks';

    protected $fillable = [
        'article_id',
        'content',
        'embedding',
        'chunk_index',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpdeskArticle::class, 'article_id');
    }
}
