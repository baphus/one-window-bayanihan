<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class CaseNotification extends Model
{
    use UsesUuid;

    protected $fillable = [
        'case_id',
        'client_email',
        'type',
        'title',
        'message',
        'data',
        'related_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }
}
