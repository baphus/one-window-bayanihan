<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralComment extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by',
        'referral_id', 'author_id',
    ];

    public function getAuditModuleName(): string
    {
        return 'referral_comment';
    }

    protected $fillable = [
        'refr_id',
        'parent_id',
        'content',
        'visibility',
        'is_edited',
        'user_id',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'refr_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
