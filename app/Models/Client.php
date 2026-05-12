<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'date_of_birth',
        'sex',
        'email',
        'contact',
        'case_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_deleted' => 'boolean',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class, 'client_id');
    }

    public function employments()
    {
        return $this->hasMany(ClientEmployment::class, 'client_id');
    }

    public function nextOfKin()
    {
        return $this->hasMany(NextOfKin::class, 'client_id');
    }
}
