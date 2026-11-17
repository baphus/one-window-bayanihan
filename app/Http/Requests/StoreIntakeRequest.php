<?php

namespace App\Http\Requests;

class StoreIntakeRequest extends StoreCaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOfw() ?? false;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['email' => $this->user()?->email]);
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'proposed_assessments' => ['required', 'array', 'min:1', 'max:8'],
        ]);
    }
}
