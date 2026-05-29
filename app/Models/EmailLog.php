<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use UsesUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
