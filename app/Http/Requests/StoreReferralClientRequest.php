<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\ReferralClientRequest as ClientRequest;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreReferralClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::AGENCY->value && $this->user()->is_active;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:10000'],
            'type' => ['required', 'string', Rule::in([
                ClientRequest::TYPE_DOCUMENT_REQUEST,
                ClientRequest::TYPE_QUESTION,
                ClientRequest::TYPE_INFORMATION_UPDATE,
            ])],
            'due_at' => ['nullable', 'date', 'after:now'],
            'checklist' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('type') === ClientRequest::TYPE_DOCUMENT_REQUEST),
                'min:'.($this->input('type') === ClientRequest::TYPE_DOCUMENT_REQUEST ? 1 : 0),
            ],
            // A free-text item, or a requirement-backed item the agency picked
            // from the referral (see ReferralClientRequestService::createRequest).
            'checklist.*' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value)) {
                        if (mb_strlen($value) > 255) {
                            $fail("The {$attribute} field must not be greater than 255 characters.");
                        }

                        return;
                    }

                    if (! is_array($value)) {
                        $fail("The {$attribute} field must be a string or a checklist item.");

                        return;
                    }

                    $item = Validator::make($value, [
                        'label' => ['required', 'string', 'max:255'],
                        'description' => ['nullable', 'string', 'max:5000'],
                        'source_requirement_id' => ['nullable', 'uuid'],
                        'source_requirement_ids' => ['nullable', 'array'],
                        'source_requirement_ids.*' => ['uuid'],
                        'canonical_key' => ['nullable', 'string', 'max:255'],
                        'sort_order' => ['nullable', 'integer', 'min:0'],
                    ]);

                    if ($item->fails()) {
                        $fail($item->errors()->first());
                    }
                },
            ],
        ];
    }
}
