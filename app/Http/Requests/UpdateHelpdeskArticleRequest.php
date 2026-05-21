<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpdeskArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $articleId = $this->route('article');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('helpdesk_articles')->ignore($articleId)],
            'content_markdown' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'string', 'exists:helpdesk_categories,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'featured' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['string', 'exists:helpdesk_tags,id'],
            'edit_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
