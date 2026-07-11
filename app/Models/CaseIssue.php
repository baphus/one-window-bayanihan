<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseIssue extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    public function getAuditModuleName(): string
    {
        return 'case_issue';
    }

    protected $table = 'case_issues';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
        'is_deleted',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function caseFiles()
    {
        return $this->hasMany(CaseFile::class, 'case_issue_id');
    }
}
