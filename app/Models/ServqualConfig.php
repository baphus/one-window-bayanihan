<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServqualConfig extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'agency_id',
        'service_name',
        'questions',
        'is_active',
        'activated_at',
    ];

    protected $casts = [
        'questions' => 'array',
        'activated_at' => 'datetime',
    ];

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
