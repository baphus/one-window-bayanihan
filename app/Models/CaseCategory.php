<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseCategory extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by'];

    public function getAuditModuleName(): string
    {
        return 'case_category';
    }

    protected $table = 'case_categories';

    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function suggestedAgencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'case_category_agency', 'case_category_id', 'agcy_id')
            ->withTimestamps();
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'category_id');
    }
}
