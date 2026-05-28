<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'agcy_id'];

    protected $fillable = [
        'name',
        'description',
        'processing_days',
        'agcy_id',
    ];

    protected $casts = [
        'processing_days' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agcy_id');
    }

    public function requirements()
    {
        return $this->hasMany(ServiceRequirement::class, 'service_id');
    }
}
