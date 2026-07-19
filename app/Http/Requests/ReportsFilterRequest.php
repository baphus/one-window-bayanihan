<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportsFilterRequest extends FormRequest
{
    private const DATE_SCOPES = [
        'case_created_at',
        'referral_created_at',
        'referral_updated_at',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('date_scope')) {
            $this->merge(['date_scope' => 'case_created_at']);
        }
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['nullable', 'string', 'date_format:Y-m-d'],
            'date_scope' => ['required', 'string', 'in:'.implode(',', self::DATE_SCOPES)],
            'agency_id' => ['nullable', 'string', 'uuid'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if ($validator->errors()->hasAny(['from', 'to']) || ! $from || ! $to) {
                return;
            }

            $fromDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
            $toDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);

            if ($fromDate > $toDate) {
                $validator->errors()->add('to', 'The end date must be on or after the start date.');
            } elseif ($fromDate->diff($toDate)->days > 366) {
                $validator->errors()->add('to', 'The report date range may not exceed one year.');
            }
        });
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            redirect()->route('reports.index')
                ->withErrors($validator)
                ->with('error', $validator->errors()->first())
        );
    }
}
