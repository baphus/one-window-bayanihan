<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskArticleRevision extends Model
{
    use HasFactory, UsesUuid;

    protected $table = 'helpdesk_article_revisions';

    protected $fillable = [
        'article_id',
        'title',
        'content_markdown',
        'excerpt',
        'edited_by',
        'edit_notes',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpdeskArticle::class, 'article_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
