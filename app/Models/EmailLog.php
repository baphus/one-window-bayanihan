<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailLog extends Model
{
    use UsesUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Provider delivery events recorded for this message.
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class);
    }
}
