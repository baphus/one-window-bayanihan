<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // OTP session is auth enough
    }

    public function rules(): array
    {
        return [
            'tracking_token' => ['required', 'string'],
            'servqual_responses' => ['required', 'array', 'min:1'],
            'servqual_responses.*.dimension' => ['required', 'string'],
            'servqual_responses.*.question_text' => ['required', 'string'],
            'servqual_responses.*.expectation' => ['required', 'integer', 'min:1', 'max:5'],
            'servqual_responses.*.perception' => ['required', 'integer', 'min:1', 'max:5'],
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
