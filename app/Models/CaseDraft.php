<?php

namespace App\Models;

use App\Casts\VersionedEncryptedPayload;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseDraft extends Model
{
    use HasFactory, UsesUuid;

    public const STATE_EDITING = 'EDITING';

    public const STATE_PUBLISHED = 'PUBLISHED';

    public const STATE_DISCARDED = 'DISCARDED';

    public const STATES = [
        self::STATE_EDITING,
        self::STATE_PUBLISHED,
        self::STATE_DISCARDED,
    ];

    protected $table = 'case_drafts';

    protected $fillable = [
        'owner_id',
        'source_client_id',
        'payload_encrypted',
        'payload_schema_version',
        'revision',
        'state',
        'published_case_id',
        'legacy_case_id',
        'published_at',
        'discarded_at',
        'consent_notice_version',
        'consent_accepted_at',
        'selected_nok_id',
        'selected_nok_evidence',
    ];

    protected $hidden = [
        'payload_encrypted',
    ];

    protected $casts = [
        'payload_encrypted' => VersionedEncryptedPayload::class,
        'payload_schema_version' => 'integer',
        'revision' => 'integer',
        'published_at' => 'datetime',
        'discarded_at' => 'datetime',
        'selected_nok_evidence' => 'array',
        'consent_accepted_at' => 'datetime',
    ];

    public function isEditing(): bool
    {
        return $this->state === self::STATE_EDITING;
    }

    public function isPublished(): bool
    {
        return $this->state === self::STATE_PUBLISHED;
    }

    public function isDiscarded(): bool
    {
        return $this->state === self::STATE_DISCARDED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [self::STATE_PUBLISHED, self::STATE_DISCARDED], true);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sourceClient()
    {
        return $this->belongsTo(Client::class, 'source_client_id');
    }

    public function publishedCase()
    {
        return $this->belongsTo(CaseFile::class, 'published_case_id');
    }
}
