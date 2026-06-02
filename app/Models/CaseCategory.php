<?php

namespace App\Models;

use App\Models\Concerns\SoftDeleteFlag;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaseCategory extends Model
{
    use HasFactory, SoftDeleteFlag, UsesUuid;

    protected $table = 'case_categories';

    protected $fillable = [
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function caseFiles()
    {
        return $this->hasMany(CaseFile::class, 'category_id');
    }
}
