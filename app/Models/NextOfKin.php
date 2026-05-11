<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'case_id',
        'first_name',
        'last_name',
        'relationship',
        'contact',
        'email',
        'address',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }
}
