<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHelpdeskArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('helpdesk_articles')],
            'content_markdown' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'string', 'exists:helpdesk_categories,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'featured' => ['nullable', 'boolean'],
            'visibility' => ['required', Rule::in(['public', 'authenticated', 'role_restricted'])],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', Rule::in(['OFW', 'CASE_MANAGER', 'AGENCY', 'ADMIN'])],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['string', 'exists:helpdesk_tags,id'],
        ];
    }
}
