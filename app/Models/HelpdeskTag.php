<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HelpdeskTag extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(HelpdeskArticle::class, 'helpdesk_article_tag', 'tag_id', 'article_id');
    }
}
