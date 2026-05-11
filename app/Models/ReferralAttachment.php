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
        'file_url',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
