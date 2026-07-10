<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'case_id',
        'agency_id',
        'referral_id',
        'service_id',
        'service_name',
        'overall_rating',
        'comments',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function servqualResponses()
    {
        return $this->hasMany(FeedbackServqualResponse::class, 'feedback_id');
    }
}
