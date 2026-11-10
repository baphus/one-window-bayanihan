<?php

namespace App\Http\Requests;

use App\Models\CaseFile;
use App\Rules\EmploymentStartAtWorkingAge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateCaseRequest extends FormRequest
{
    public const EDITABLE_FIELDS = [
        'intake_date', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex',
        'email', 'contact_number', 'civil_status', 'civil_status_other', 'deployment_mode', 'employment_status',
        'address_region', 'address_province', 'address_city_municipality', 'address_barangay', 'address_street',
        'address_region_code', 'address_province_code', 'address_city_municipality_code', 'address_barangay_code',
        'employment_employer_name', 'employment_employer_address', 'employment_position', 'employment_country',
        'employment_last_country', 'employment_last_position', 'employment_start_date', 'employment_end_date',
        'employment_date_of_arrival', 'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email',
        'summary', 'vulnerability_indicator', 'nok_vulnerability_indicator',
    ];

    public function authorize(): bool
    {
        $case = CaseFile::find($this->route('case'));
        $actor = $this->user();

        return $case && $actor && ($actor->isAdmin() || ($actor->isCaseManager() && $case->assigned_case_manager_id === $actor->id));
    }

    public function rules(): array
    {
        $case = CaseFile::findOrFail($this->route('case'));
        $source = new StoreCaseRequest;
        $source->setUserResolver(fn () => $this->user());
        $source->replace(array_merge($case->only(self::EDITABLE_FIELDS), $this->all()));
        $rules = array_intersect_key($source->rules(), array_flip(self::EDITABLE_FIELDS));
        foreach ($rules as &$rule) {
            array_unshift($rule, 'sometimes');
        }
        unset($rule);
        $dob = $this->input('date_of_birth', $case->date_of_birth?->toDateString());
        $start = $this->input('employment_start_date', $case->employment_start_date?->toDateString());
        $rules['date_of_birth'] = ['sometimes', 'required', 'date_format:Y-m-d'];
        $rules['employment_start_date'] = ['sometimes', 'nullable', 'date', 'before_or_equal:today', new EmploymentStartAtWorkingAge($dob)];
        $rules['employment_end_date'] = ['sometimes', 'nullable', 'date', 'before_or_equal:today'];
        $rules['employment_date_of_arrival'] = ['sometimes', 'nullable', 'date', 'before_or_equal:today'];
        if ($start) {
            $rules['employment_end_date'][] = 'after_or_equal:'.$start;
        }
        if ($dob) {
            $rules['employment_date_of_arrival'][] = 'after_or_equal:'.$dob;
        }
        $rules['correction_reason'] = ['required', 'string', 'max:1000'];
        foreach (['ofw_user_id', 'assigned_case_manager_id', 'user_id', 'client_id', 'client_type', 'consent_method', 'consent_text_version', 'consent_evidence', 'consent_accepted_by', 'consent_given_at', 'proposed_assessments', 'proposed_assessment_by', 'proposed_assessment_at', 'confirmed_assessments', 'confirmed_assessment_by', 'confirmed_assessment_at', 'return_reason', 'missing_document_reasons', 'requested_document_slots', 'document_request_reason', 'document_requested_by', 'document_requested_at', 'reviewed_document_ids', 'last_correction_reason', 'status', 'reviewed_by', 'reviewed_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $case = CaseFile::findOrFail($this->route('case'));
            $dob = $this->input('date_of_birth', $case->date_of_birth?->toDateString());
            $intake = $this->input('intake_date', $case->intake_date?->toDateString());
            if ($dob && $intake && ! $validator->errors()->has('date_of_birth') && ! $validator->errors()->has('intake_date')) {
                $age = Carbon::parse($dob)->diffInYears(Carbon::parse($intake), false);
                if ($age < 15 || $age > 100) {
                    $validator->errors()->add('date_of_birth', 'Age at intake must be between 15 and 100.');
                }
            }
        });
    }
}
