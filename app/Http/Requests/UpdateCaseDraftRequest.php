<?php

namespace App\Http\Requests;

use App\DTOs\CaseDraftPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('save', $this->route('draft')) ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'client_source' => ['required', Rule::in(['NEW', 'EXISTING'])],
            'source_client_id' => ['bail', 'nullable', 'uuid', Rule::exists('clients', 'id')->where(fn ($query) => $query->whereRaw('is_deleted IS FALSE'))],
            'selected_nok_id' => $this->selectedNokRules(),
            'client_type' => ['nullable', Rule::in(['OFW', 'NEXT_OF_KIN'])],
            'category_ids' => ['nullable', 'array', 'max:100'],
            'category_ids.*' => ['bail', 'uuid', 'distinct', Rule::exists('case_categories', 'id')->where(fn ($query) => $query->whereRaw('is_active IS TRUE AND is_deleted IS FALSE'))],
            'case_issue_id' => ['bail', 'nullable', 'uuid', Rule::exists('case_issues', 'id')->where(fn ($query) => $query->whereRaw('is_active IS TRUE AND is_deleted IS FALSE'))],
        ];

        if ($this->input('client_source') === 'EXISTING') {
            foreach (['client', 'address', 'employment', 'next_of_kin'] as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
    }

    public function payload(): array
    {
        return CaseDraftPayload::fromArray($this->except('expected_revision'))->toArray();
    }

    private function selectedNokRules(): array
    {
        $rules = ['bail', 'nullable', 'uuid'];

        if ($this->input('client_source') === 'EXISTING') {
            $rules[] = Rule::exists('next_of_kin', 'id')->where(fn ($query) => $query
                ->whereRaw('is_deleted IS FALSE'));
        }

        return $rules;
    }
}
