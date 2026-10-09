<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use HasFactory, UsesUuid;

    /**
     * Only created_at is used — no updated_at.
     */
    const UPDATED_AT = null;

    public static array $auditExclude = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
        'survey_invitation_id', 'survey_form_id', 'case_id', 'agency_id',
    ];

    public function getAuditModuleName(): string
    {
        return 'survey_response';
    }

    protected $fillable = [
        'survey_invitation_id',
        'survey_question_id',
        'answer',
        'selected_options',
    ];

    protected $casts = [
        'selected_options' => 'array',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(SurveyInvitation::class, 'survey_invitation_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
