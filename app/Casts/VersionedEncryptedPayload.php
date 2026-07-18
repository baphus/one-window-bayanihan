<?php

namespace App\Casts;

use App\Encryption\VersionedPayloadEncryptor;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

final class VersionedEncryptedPayload implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        return $value === null ? null : app(VersionedPayloadEncryptor::class)->decrypt($value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value === null ? null : app(VersionedPayloadEncryptor::class)->encrypt($value);
    }
}
