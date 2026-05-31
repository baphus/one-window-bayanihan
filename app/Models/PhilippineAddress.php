<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhilippineAddress extends Model
{
    protected $fillable = ['type', 'code', 'name', 'parent_code'];
}
