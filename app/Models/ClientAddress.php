<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'client_id',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
