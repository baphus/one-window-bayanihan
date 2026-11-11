<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceIntakeDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');
        $case = $case instanceof CaseFile ? $case : CaseFile::find($case);

        return $case && $this->user()?->isOfw()
            && $case->ofw_user_id === $this->user()->id
            && ! in_array($case->status, ['CLOSED', 'ARCHIVED'], true)
            && IntakeDocumentService::outstandingSlots($case->document_requirements) !== [];
    }

    public function rules(): array
    {
        return [
            'documents' => ['required', 'array:'.implode(',', IntakeDocumentService::REQUIREMENT_KEYS), 'min:1'],
            'documents.*' => ['required', 'file', 'max:'.config('file-uploads.max_size', 20480), 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }
}
