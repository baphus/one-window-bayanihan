<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Canonical avatar upload validation.
 *
 * Every avatar entry point (invite registration, OFW registration, staff
 * profile, OFW profile) validates through this class so a photo accepted at
 * registration is also accepted on the profile page. The rule set previously
 * drifted between ProfileUpdateRequest (max:2048, no gif) and
 * ProfilePictureRequest (max:5120, gif allowed); this is the single source.
 *
 * The 32x32 floor is mirrored independently in
 * CloudinaryAvatarService::validateImageFile(), which sniffs decoded content
 * rather than trusting the client-declared MIME type.
 */
class AvatarUploadRequest extends FormRequest
{
    /**
     * Registration and profile callers are optional uploads; the profile page
     * additionally accepts the legacy `avatar` field name so the current
     * Profile/Edit.jsx payload keeps working during the transition.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_picture' => [
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=32,min_height=32',
            ],
            'avatar' => [
                'nullable',
                'image',
                'mimes:jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=32,min_height=32',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile_picture.image' => 'The file must be an image.',
            'profile_picture.mimes' => 'Profile picture must be a JPEG, PNG, or WebP file.',
            'profile_picture.max' => 'Profile picture must not exceed 2MB.',
            'profile_picture.dimensions' => 'Profile picture must be at least 32x32 pixels.',
            'avatar.image' => 'The file must be an image.',
            'avatar.mimes' => 'Profile picture must be a JPEG, PNG, or WebP file.',
            'avatar.max' => 'Profile picture must not exceed 2MB.',
            'avatar.dimensions' => 'Profile picture must be at least 32x32 pixels.',
        ];
    }

    /**
     * Resolve the uploaded file regardless of which field name the caller used.
     */
    public function uploadedAvatar(): ?UploadedFile
    {
        return $this->file('profile_picture') ?? $this->file('avatar');
    }
}
