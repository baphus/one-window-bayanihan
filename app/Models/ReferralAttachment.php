<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralAttachment extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'referral_id',
        'file_name',
        'file_path',
        'file_type',
        'size',
        'user_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
