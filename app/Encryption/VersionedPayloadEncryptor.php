<?php

namespace App\Encryption;

use App\Exceptions\CaseDraftPayloadDecryptionException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use JsonException;

final class VersionedPayloadEncryptor
{
    private const ENVELOPE_VERSION = 1;

    public function encrypt(array $payload): string
    {
        $key = $this->key(config('app.key'));
        $keyId = (string) (config('encryption.key_id') ?: $this->derivedKeyId($key));

        try {
            return json_encode([
                'v' => self::ENVELOPE_VERSION,
                'kid' => $keyId,
                'ct' => (new Encrypter($key, config('app.cipher')))->encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \UnexpectedValueException('Case draft payload encoding failed.', 0, $exception);
        }
    }

    public function decrypt(string $ciphertext): array
    {
        try {
            $envelope = $this->envelope($ciphertext);
            $keyId = $envelope['kid'];
            $key = $this->keyForId($keyId);
            $payload = json_decode((new Encrypter($key, config('app.cipher')))->decryptString($envelope['ct']), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw new \UnexpectedValueException('Payload is not an object.');
            }

            return $payload;
        } catch (\Throwable $exception) {
            $keyId = is_array($envelope ?? null) ? ($envelope['kid'] ?? 'unknown') : 'unknown';
            $failure = new CaseDraftPayloadDecryptionException((string) $keyId, $exception);
            Log::error($failure->getMessage());
            throw $failure;
        }
    }

    public function isCurrentKey(string $ciphertext): bool
    {
        $envelope = $this->envelope($ciphertext);

        return $envelope['kid'] === $this->currentKeyId();
    }

    public function reencrypt(string $ciphertext): string
    {
        return $this->encrypt($this->decrypt($ciphertext));
    }

    private function keyForId(string $keyId): string
    {
        foreach ($this->configuredKeys() as $candidateId => $key) {
            if (hash_equals((string) $candidateId, $keyId)) {
                return $key;
            }
        }

        throw new \UnexpectedValueException('Unknown encryption key ID.');
    }

    /** @return array{v: int, kid: string, ct: string} */
    private function envelope(string $ciphertext): array
    {
        try {
            $envelope = json_decode($ciphertext, true, 3, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \UnexpectedValueException('Malformed case draft encryption envelope.', 0, $exception);
        }

        if (! is_array($envelope)
            || ! array_key_exists('v', $envelope)
            || ! array_key_exists('kid', $envelope)
            || ! array_key_exists('ct', $envelope)
            || ! is_int($envelope['v'])
            || $envelope['v'] !== self::ENVELOPE_VERSION
            || ! is_string($envelope['kid'])
            || $envelope['kid'] === ''
            || ! is_string($envelope['ct'])
            || $envelope['ct'] === '') {
            throw new \UnexpectedValueException('Unsupported or invalid case draft encryption envelope.');
        }

        return $envelope;
    }

    private function currentKeyId(): string
    {
        $key = $this->key(config('app.key'));

        return (string) (config('encryption.key_id') ?: $this->derivedKeyId($key));
    }

    private function configuredKeys(): array
    {
        $current = $this->key(config('app.key'));
        $keys = [];
        $keys[$this->currentKeyId()] = $current;
        $ids = config('encryption.previous_key_ids', []);

        foreach ((array) config('app.previous_keys', []) as $index => $previous) {
            $key = $this->key($previous);
            $keys[(string) ($ids[$index] ?? $this->derivedKeyId($key))] = $key;
        }

        return $keys;
    }

    private function key(?string $key): string
    {
        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('APP_KEY is required for case draft encryption.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    private function derivedKeyId(string $key): string
    {
        return 'sha256:'.substr(hash('sha256', $key), 0, 16);
    }
}
