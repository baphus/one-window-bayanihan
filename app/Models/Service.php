<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function agencies()
    {
        return $this->belongsToMany(Agency::class, 'agency_service')
            ->withPivot(['required_documents', 'processing_days'])
            ->withTimestamps();
    }
}
