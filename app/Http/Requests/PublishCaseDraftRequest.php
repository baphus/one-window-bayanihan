<?php

namespace App\Http\Requests;

use App\DTOs\CaseDraftPayload;
use Illuminate\Foundation\Http\FormRequest;

class PublishCaseDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish', $this->route('draft')) ?? false;
    }

    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }

    public function validatePayload(array $payload): array
    {
        return CaseDraftPayload::fromArray($payload)->validateForPublish()->toArray();
    }
}
