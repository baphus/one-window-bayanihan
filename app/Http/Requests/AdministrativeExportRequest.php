<?php

namespace App\Http\Requests;

use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Services\AuditLogger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdministrativeExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN->value;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'agency_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('agencies', 'id')->where('is_deleted', false)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unsupported = array_diff(array_keys($this->all()), ['from', 'to', 'agency_id', '_token']);
            if ($unsupported !== []) {
                $validator->errors()->add('scope', 'Unsupported export parameters were supplied.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): void
    {
        try {
            $this->audit('REJECTED', ['reason' => 'invalid_input', 'scope' => $this->safeValidatedScope()]);
        } catch (\Throwable $e) {
            throw new HttpResponseException(response()->json(['message' => 'Export audit is unavailable; no workbook was generated.'], 503));
        }

        throw new HttpResponseException(response()->json([
            'message' => 'The selected export dates or agency are invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    private function safeValidatedScope(): array
    {
        return [
            'from' => is_string($this->input('from')) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $this->input('from')) ? $this->input('from') : null,
            'to' => is_string($this->input('to')) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $this->input('to')) ? $this->input('to') : null,
            'agency_id' => is_string($this->input('agency_id')) && preg_match('/^[0-9a-f-]{36}$/i', $this->input('agency_id')) ? $this->input('agency_id') : null,
        ];
    }

    public function correlationId(): string
    {
        if (! $this->attributes->has('administrative_export_correlation')) {
            $this->attributes->set('administrative_export_correlation', (string) Str::uuid());
        }

        return $this->attributes->get('administrative_export_correlation');
    }

    private function audit(string $outcome, array $scope): void
    {
        AuditLogger::export()->by($this->user())->module(AuditModule::DATA_EXPORT)
            ->description('Administrative export '.$outcome.'.')
            ->context(null, ['report' => 'administrative', 'correlation_id' => $this->correlationId(), 'outcome' => $outcome, ...$scope])
            ->write();
    }
}
