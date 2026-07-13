<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    use HasFactory, UsesUuid;

    public const TYPE_LIKERT = 'likert';
    public const TYPE_TEXT = 'text';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RATING = 'rating';

    public const TYPES = [
        self::TYPE_LIKERT,
        self::TYPE_TEXT,
        self::TYPE_RADIO,
        self::TYPE_CHECKBOX,
        self::TYPE_RATING,
    ];

    public const LIKERT_LABELS = [
        1 => 'Strongly Disagree',
        2 => 'Disagree',
        3 => 'Neutral',
        4 => 'Agree',
        5 => 'Strongly Agree',
    ];

    protected $fillable = [
        'survey_form_id',
        'type',
        'label',
        'options',
        'is_required',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'order' => 'integer',
    ];

    public function surveyForm(): BelongsTo
    {
        return $this->belongsTo(SurveyForm::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
