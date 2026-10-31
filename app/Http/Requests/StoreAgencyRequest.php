<?php

namespace App\Http\Requests;

use App\Rules\GoogleMapsLink;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'string'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_tel' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'logo_url' => ['nullable', 'image', 'max:2048'],
            'location_query' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'map_link' => ['nullable', 'string', 'max:2048', new GoogleMapsLink],
        ];
    }
}
