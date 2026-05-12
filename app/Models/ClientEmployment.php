<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientEmployment extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

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
        'is_deleted' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
