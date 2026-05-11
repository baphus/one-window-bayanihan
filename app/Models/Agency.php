<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'name',
        'description',
        'contact_info',
        'map_link',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'agcy_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'agcy_id');
    }
}
