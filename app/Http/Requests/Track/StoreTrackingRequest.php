<?php

namespace App\Http\Requests\Track;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingRequest extends FormRequest
{
    /**
     * The public tracking portal requires no authentication.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors the inline validation previously in TrackController::sendOtp().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tracker_number' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ];
    }
}
