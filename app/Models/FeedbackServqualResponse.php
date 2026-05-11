<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackServqualResponse extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'feedback_id',
        'question_id',
        'question_text',
        'dimension',
        'expectation',
        'perception',
    ];

    public function feedback()
    {
        return $this->belongsTo(Feedback::class, 'feedback_id');
    }
}
