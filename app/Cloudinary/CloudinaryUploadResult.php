<?php

namespace App\Cloudinary;

class CloudinaryUploadResult
{
    public function __construct(
        private readonly array $result,
    ) {}

    public function getSecurePath(): string
    {
        return $this->result['secure_url'] ?? '';
    }

    public function getSecureUrl(): string
    {
        return $this->result['secure_url'] ?? '';
    }

    public function getPublicId(): string
    {
        return $this->result['public_id'] ?? '';
    }
}
