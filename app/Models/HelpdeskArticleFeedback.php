<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskArticleFeedback extends Model
{
    use HasFactory, UsesUuid;

    protected $table = 'helpdesk_article_feedback';

    protected $fillable = [
        'article_id',
        'user_id',
        'helpful',
        'comment',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpdeskArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
