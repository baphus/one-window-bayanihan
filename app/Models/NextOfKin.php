<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

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
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'phone_number' => EncryptedString::class,
        'email' => EncryptedString::class,
        'full_address' => EncryptedString::class,
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
