<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['OPEN', 'CLOSED', 'ARCHIVED'])],
            'client_type' => ['required', Rule::in(CaseFile::CLIENT_TYPES)],
            'vulnerability_indicator' => ['nullable', 'string', Rule::in(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'])],
            'nok_vulnerability_indicator' => ['nullable', 'string', Rule::in(['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person', 'None'])],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
