<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use App\Rules\EmploymentStartAtWorkingAge;
use App\Rules\ServedRegion;
use App\Rules\VulnerabilityRule;
use App\Services\IntakeDocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isAdmin() || $user->isCaseManager() || $user->isOfw());
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isOfw()) {
            $this->merge(['intake_date' => now(config('app.operating_timezone', 'Asia/Manila'))->toDateString()]);
        }
        foreach (['vulnerability_indicator', 'nok_vulnerability_indicator'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => VulnerabilityRule::normalize($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        $public = $this->user()?->isOfw();
        $rules = [
            'client_type' => ['required', Rule::in($this->user()?->isCaseManager() ? ['OFW', 'NEXT_OF_KIN'] : ['OFW'])],
            'intake_date' => ['required', 'date_format:Y-m-d'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:intake_date'],
            'sex' => ['required', Rule::in(['MALE', 'FEMALE'])],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'civil_status' => ['required', Rule::in(CaseFile::CIVIL_STATUSES)],
            'civil_status_other' => ['required_if:civil_status,OTHER', 'nullable', 'string', 'max:255'],
            'deployment_mode' => ['required', Rule::in(CaseFile::DEPLOYMENT_MODES)],
            'employment_status' => ['required', Rule::in(CaseFile::EMPLOYMENT_STATUSES)],
            'address_region' => ['required', 'string', 'max:255', new ServedRegion],
            'address_province' => ['nullable', 'string', 'max:255'],
            'address_city_municipality' => ['required', 'string', 'max:255'],
            'address_barangay' => ['required', 'string', 'max:255'],
            'address_street' => ['nullable', 'string', 'max:1000'],
            'employment_employer_name' => ['nullable', 'string', 'max:255'],
            'employment_employer_address' => ['nullable', 'string', 'max:1000'],
            'employment_position' => ['nullable', 'string', 'max:255'],
            'employment_country' => ['nullable', 'string', 'max:255'],
            'employment_last_country' => ['nullable', 'string', 'max:255'],
            'employment_last_position' => ['nullable', 'string', 'max:255'],
            'employment_start_date' => ['nullable', 'date', 'after_or_equal:date_of_birth', 'before_or_equal:today', new EmploymentStartAtWorkingAge($this->input('date_of_birth'))],
            'employment_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date', 'before_or_equal:today'],
            'employment_date_of_arrival' => ['nullable', 'date', 'after_or_equal:date_of_birth', 'before_or_equal:today'],
            'summary' => ['required', 'string', 'max:5000'],
            'vulnerability_indicator' => ['required', 'string', 'max:255', new VulnerabilityRule],
            'nok_vulnerability_indicator' => [$public ? 'prohibited' : 'nullable', 'string', 'max:255', new VulnerabilityRule],
            'proposed_assessments' => ['nullable', 'array', 'max:8'],
            'proposed_assessments.*' => ['required', 'distinct', Rule::in(CaseFile::ASSESSMENTS)],
            'proposed_assessment_other' => ['required_if:proposed_assessments.*,OTHER', 'nullable', 'string', 'max:2000'],
            'return_reason' => ['required', Rule::in(IntakeDocumentService::RETURN_REASONS)],
            'documents' => ['nullable', 'array:'.implode(',', IntakeDocumentService::SLOTS)],
            'documents.*' => ['file', 'max:'.config('file-uploads.max_size', 20480), 'mimes:pdf,jpg,jpeg,png,webp'],
            'consent_text_version' => ['required', Rule::in([IntakeDocumentService::CONSENT_VERSION])],
            'consent' => $public ? ['required', 'accepted'] : ['prohibited'],
            'consent_method' => $public ? ['prohibited'] : ['required', Rule::in(['WRITTEN', 'RECORDED'])],
            'consent_evidence' => $public ? ['prohibited'] : ['required', 'string', 'max:2000'],
            'missing_document_reasons' => $public ? ['prohibited'] : ['nullable', 'array:passport_front_page,arrival_stamp,employment_evidence,distress_certificate,termination_letter,sworn_affidavit'],
            'missing_document_reasons.*' => ['required', 'string', 'max:1000'],
            'staff_summary' => $public ? ['prohibited'] : ['nullable', 'string', 'max:5000'],
        ];
        foreach (['nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email'] as $field) {
            $rules[$field] = $public ? ['prohibited'] : ['required_if:client_type,NEXT_OF_KIN', 'nullable', $field === 'nok_email' ? 'email' : 'string', 'max:255'];
        }
        foreach (['region', 'province', 'city_municipality', 'barangay'] as $part) {
            $rules['address_'.$part.'_code'] = ['nullable', 'string', 'max:20'];
        }
        foreach (['assigned_case_manager_id', 'last_correction_reason', 'document_requested_by', 'document_requested_at', 'requested_document_slots', 'reviewed_document_ids', 'ofw_user_id', 'user_id', 'client_id', 'selected_client_id', 'new_ofw', 'next_of_kin', 'client', 'category_id', 'category_ids', 'case_issue_id', 'confirmed_assessments', 'confirmed_assessment_by', 'confirmed_assessment_at', 'proposed_assessment_by', 'proposed_assessment_at', 'consent_accepted_by', 'consent_given_at', 'reviewed_by', 'reviewed_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (in_array('OTHER', (array) $this->input('proposed_assessments', []), true) && ! $this->filled('proposed_assessment_other')) {
                $validator->errors()->add('proposed_assessment_other', 'Explain the Other assessment.');
            }
            if (! $validator->errors()->has('date_of_birth') && ! $validator->errors()->has('intake_date') && $this->filled('date_of_birth') && $this->filled('intake_date')) {
                $age = Carbon::parse($this->input('date_of_birth'))->diffInYears(Carbon::parse($this->input('intake_date')));
                if ($age < 15 || $age > 100) {
                    $validator->errors()->add('date_of_birth', 'Age at intake must be between 15 and 100.');
                }
            }
            $slots = array_keys($this->file('documents', []));
            foreach (IntakeDocumentService::missingSlots($slots, (string) $this->input('return_reason')) as $slot) {
                if ($this->user()?->isOfw() || ! $this->filled('missing_document_reasons.'.$slot)) {
                    $validator->errors()->add('documents.'.$slot, 'Required document missing. Assisted intake requires a missing-document reason.');
                }
            }
        });
    }
}
