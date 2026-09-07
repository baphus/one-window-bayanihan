<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'], 'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'in:user,bot'], 'history.*.text' => ['required', 'string', 'max:1000'],
            'lastContext' => ['nullable', 'array'],
            'lastContext.source_type' => ['required_with:lastContext', 'string', 'max:30'],
            'lastContext.source_label' => ['required_with:lastContext', 'string', 'max:150'],
            'lastContext.article_title' => ['required_with:lastContext', 'string', 'max:250'],
        ];
    }
}
