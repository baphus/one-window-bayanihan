<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientEmployment extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    public static array $auditExclude = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'client_id'];

    public function getAuditModuleName(): string
    {
        return 'client_employment';
    }

    protected $fillable = [
        'client_id',
        'employer_name',
        'position',
        'country',
        'start_date',
        'end_date',
        'last_country',
        'last_position',
        'date_of_arrival',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'date_of_arrival' => 'date',
        'employer_name' => EncryptedString::class,
        'position' => EncryptedString::class,
        'last_position' => EncryptedString::class,
        'country' => EncryptedString::class,
        'last_country' => EncryptedString::class,
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
