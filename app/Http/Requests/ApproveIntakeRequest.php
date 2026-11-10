<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isCaseManager() || $this->user()->isAdmin());
    }

    public function rules(): array
    {
        return [
            'confirmed_assessments' => ['required', 'array', 'min:1', 'max:8'],
            'confirmed_assessments.*' => ['required', 'distinct', Rule::in(CaseFile::ASSESSMENTS)],
            'confirmed_assessment_other' => [Rule::requiredIf(in_array('OTHER', (array) $this->input('confirmed_assessments', []), true)), 'nullable', 'string', 'max:2000'],
            'staff_summary' => ['nullable', 'string', 'max:5000'],
            'reviewed_document_ids' => ['required', 'array', 'min:4'],
            'reviewed_document_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
