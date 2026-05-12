<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'client_id',
        'first_name',
        'middle_initial',
        'last_name',
        'is_primary',
        'relationship',
        'phone_number',
        'email',
        'full_address',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
