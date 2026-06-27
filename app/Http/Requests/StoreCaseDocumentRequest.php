<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $caseId = $this->route('case');
        $case = CaseFile::find($caseId);

        if (! $case) {
            return false;
        }

        $user = $this->user();

        if ($case->user_id === $user->id) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCaseManager()) {
            return true;
        }

        $hasActiveReferral = $case->referrals()
            ->where('agcy_id', $user->agcy_id)
            ->whereNotIn('status', ['COMPLETED', 'REJECTED'])
            ->exists();

        return $hasActiveReferral;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,png,doc,docx'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is invalid.',
            'file.max' => 'The file must not be larger than 20MB.',
            'file.mimes' => 'The file must be a PDF, JPG, PNG, DOC, or DOCX file.',
            'description.max' => 'The description must not exceed 500 characters.',
        ];
    }
}
