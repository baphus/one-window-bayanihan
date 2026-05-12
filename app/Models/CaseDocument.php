<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseDocument extends Model
{
    use HasFactory, UsesUuid, SoftDeleteFlag;

    protected $fillable = [
        'file_name',
        'file_path',
        'file_type',
        'case_id',
        'user_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
