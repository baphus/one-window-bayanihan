<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskArticle extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    protected $table = 'helpdesk_articles';

    protected $fillable = [
        'title',
        'slug',
        'content_markdown',
        'excerpt',
        'category_id',
        'status',
        'featured',
        'author_id',
        'published_at',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'is_deleted' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(HelpdeskTag::class, 'helpdesk_article_tag', 'article_id', 'tag_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(HelpdeskArticleRevision::class, 'article_id')->orderBy('created_at', 'desc');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(HelpdeskArticleFeedback::class, 'article_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(HelpdeskArticleChunk::class, 'article_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('is_deleted', false);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}
