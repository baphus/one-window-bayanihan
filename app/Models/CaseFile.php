<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseFile extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $table = 'cases';

    protected $fillable = [
        'case_number',
        'client_type',
        'tracker_number',
        'summary',
        'status',
        'consent_given_at',
        'user_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'consent_given_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'case_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'case_id');
    }

}
