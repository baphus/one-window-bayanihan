<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'refr_id'];

    public function getAuditModuleName(): string
    {
        return 'milestone';
    }

    protected $fillable = [
        'title',
        'description',
        'requirements',
        'refr_id',
        'user_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'requirements' => 'array',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'refr_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
