<?php

namespace App\Models;

use App\Models\CaseIssue;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseFile extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    public function getAuditModuleName(): string
    {
        return 'case';
    }

    protected $table = 'cases';

    protected $fillable = [
        'case_number',
        'client_type',
        'vulnerability_indicator',
        'nok_vulnerability_indicator',
        'tracker_number',
        'summary',
        'status',
        'closed_at',
        'consent_given_at',
        'user_id',
        'client_id',
        'category_id',
        'case_issue_id',
        'draft_client_data',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'closed_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'draft_client_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function category()
    {
        return $this->belongsTo(CaseCategory::class, 'category_id');
    }

    public function caseIssue()
    {
        return $this->belongsTo(CaseIssue::class, 'case_issue_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'case_id');
    }

    public function documents()
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function comments()
    {
        return $this->hasMany(CaseComment::class, 'case_id');
    }
}
