<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_picture' => ['required', 'image', 'mimes:jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_picture.max' => 'Profile picture must not exceed 2MB.',
            'profile_picture.mimes' => 'Profile picture must be a JPEG or PNG file.',
            'profile_picture.image' => 'The file must be an image.',
        ];
    }
}
