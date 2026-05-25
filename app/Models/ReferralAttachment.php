<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralAttachment extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    protected $fillable = [
        'referral_id',
        'file_name',
        'file_path',
        'file_type',
        'size',
        'user_id',
        'replaces_id',
        'version_group_id',
        'is_archived',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_archived' => 'boolean',
    ];

    protected $appends = [
        'file_url',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fileUrl(): Attribute
    {
        return Attribute::get(fn () => $this->file_path
            ? Storage::url($this->file_path)
            : null);
    }

    public function replacedBy()
    {
        return $this->hasOne(self::class, 'replaces_id');
    }

    public function replaces()
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function siblingVersions()
    {
        return $this->hasMany(self::class, 'version_group_id', 'version_group_id');
    }
}
