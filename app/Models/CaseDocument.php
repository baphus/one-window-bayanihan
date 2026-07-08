<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseDocument extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    protected $fillable = [
        'file_name',
        'file_path',
        'file_type',
        'size',
        'case_id',
        'user_id',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $appends = [
        'file_url',
    ];

    protected $hidden = [
        'is_deleted',
        'deleted_at',
        'deleted_by',
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

    public function fileUrl(): Attribute
    {
        return Attribute::get(fn () => $this->file_path
            ? app(StorageService::class)->temporaryUrl($this->file_path, 24)
            : null);
    }
}
