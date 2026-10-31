<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class HelpdeskArticleFeedback extends Model
{
    use UsesUuid;

    protected $fillable = ['article_slug', 'identity_hash', 'vote'];
}
