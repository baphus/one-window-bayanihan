<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'client_id'];

    public function getAuditModuleName(): string
    {
        return 'client_address';
    }

    protected $fillable = [
        'client_id',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street',
    ];

    protected $casts = [
        'street' => EncryptedString::class,
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
