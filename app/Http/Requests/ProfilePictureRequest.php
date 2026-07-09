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
            'profile_picture' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120', 'dimensions:min_width=32,min_height=32'],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_picture.max' => 'Profile picture must not exceed 5MB.',
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, GIF, or WebP file.',
            'profile_picture.image' => 'The file must be an image.',
        ];
    }
}
