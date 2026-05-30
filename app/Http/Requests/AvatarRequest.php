<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by UserAvatarController inline check
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'Avatar must not exceed 2MB.',
            'avatar.mimes' => 'Avatar must be a JPEG or PNG file.',
            'avatar.image' => 'The file must be an image.',
        ];
    }
}
