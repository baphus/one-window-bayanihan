<?php

namespace App\DTOs;

use App\Models\Client;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class CaseDraftPayload
{
    public const SCHEMA_VERSION = 1;

    public const MAX_BYTES = 256_000;

    public const MAX_CATEGORIES = 100;

    public const MAX_NOKS = 100;

    private const MIDDLE_INITIAL_MAX_LENGTH = 1;

    private const PHONE_MAX_LENGTH = 50;

    private const POSTGRES_INT4_MAX = 2_147_483_647;

    /**
     * Notice versions accepted by the server.  Payloads cannot introduce a
     * notice version merely by choosing an otherwise valid-looking string.
     */
    private const CURRENT_NOTICE_VERSION = 'v1';

    private const APPROVED_NOTICE_VERSIONS = [self::CURRENT_NOTICE_VERSION];

    private const TOP_LEVEL = [
        'schema_version', 'client_source', 'source_client_id', 'client_type',
        'vulnerability_indicator', 'nok_vulnerability_indicator', 'summary',
        'case_issue_id', 'category_ids', 'client', 'address', 'employment',
        'next_of_kin', 'selected_nok_id', 'consent',
    ];

    private const CLIENT_FIELDS = ['first_name', 'last_name', 'middle_initial', 'suffix', 'date_of_birth', 'sex', 'email', 'contact_number'];

    private const ADDRESS_FIELDS = ['region', 'province', 'city_municipality', 'barangay', 'street'];

    private const EMPLOYMENT_FIELDS = ['employer_name', 'position', 'country', 'start_date', 'end_date', 'last_country', 'last_position', 'date_of_arrival'];

    private const NOK_FIELDS = ['id', 'first_name', 'middle_initial', 'last_name', 'is_primary', 'relationship', 'phone_number', 'email', 'full_address', 'region', 'province', 'city_municipality', 'barangay', 'street', 'sort_order'];

    private function __construct(private readonly array $values) {}

    public static function fromArray(array $payload): self
    {
        $payload = self::normalize($payload);
        $version = $payload['schema_version'] ?? self::SCHEMA_VERSION;
        if ($version !== self::SCHEMA_VERSION) {
            self::fail('schema_version', 'Unsupported draft payload schema version.');
        }

        $source = $payload['client_source'] ?? null;
        if (! in_array($source, ['NEW', 'EXISTING'], true)) {
            self::fail('client_source', 'Client source must be NEW or EXISTING.');
        }

        if ($source === 'NEW' && ! empty($payload['source_client_id'])) {
            self::fail('source_client_id', 'New drafts cannot contain a source client.');
        }
        if ($source === 'EXISTING' && empty($payload['source_client_id'])) {
            self::fail('source_client_id', 'An existing client draft requires a source client.');
        }
        if (isset($payload['source_client_id'])) {
            self::assertUuid($payload['source_client_id'], 'source_client_id');
        }

        if (isset($payload['client_type']) && ! in_array($payload['client_type'], ['OFW', 'NEXT_OF_KIN'], true)) {
            self::fail('client_type', 'Client type must be OFW or NEXT_OF_KIN.');
        }
        self::assertString($payload, 'vulnerability_indicator', 255);
        self::assertString($payload, 'nok_vulnerability_indicator', 255);
        self::assertString($payload, 'summary', 5000);
        self::assertUuidIfPresent($payload, 'case_issue_id');
        self::assertUuidList($payload['category_ids'] ?? [], 'category_ids', self::MAX_CATEGORIES, true);
        self::assertClient($payload['client'] ?? null, $source);
        self::assertAddress($payload['address'] ?? null);
        self::assertEmployment($payload['employment'] ?? null);
        self::assertNoks($payload['next_of_kin'] ?? [], $source);
        self::assertUuidIfPresent($payload, 'selected_nok_id');
        self::assertConsent($payload['consent'] ?? null);

        if ($source === 'NEW' && isset($payload['selected_nok_id'])
            && ! in_array($payload['selected_nok_id'], array_column($payload['next_of_kin'] ?? [], 'id'), true)) {
            self::fail('selected_nok_id', 'Selected next of kin must belong to the draft payload.');
        }

        return new self($payload);
    }

    public function validateForPublish(): self
    {
        $data = $this->values;
        foreach (['client_type', 'category_ids'] as $field) {
            if (empty($data[$field])) {
                self::fail($field, 'This field is required before publishing.');
            }
        }

        if ($this->clientSource() === 'NEW') {
            self::assertPublishDbLimits($data);
            foreach (['first_name', 'last_name', 'date_of_birth', 'sex', 'contact_number'] as $field) {
                if (self::blank($data['client'][$field] ?? null)) {
                    self::fail('client.'.$field, 'This field is required before publishing.');
                }
            }
            if ($this->clientType() === 'OFW' && self::blank($data['client']['email'] ?? null)) {
                self::fail('client.email', 'An email address is required before publishing.');
            }
            foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
                if (self::blank($data['address'][$field] ?? null)) {
                    self::fail('address.'.$field, 'This field is required before publishing.');
                }
            }
            if (empty($data['consent']['accepted_at'])) {
                self::fail('consent.accepted_at', 'Consent is required before publishing.');
            }
        } else {
            $client = Client::query()
                ->whereKey($this->sourceClientId())
                ->where('is_deleted', false)
                ->with(['addresses' => fn ($query) => $query->where('is_deleted', false)->latest()])
                ->first();

            if (! $client) {
                self::fail('source_client_id', 'The source client is not active.');
            }

            foreach (['first_name', 'last_name', 'date_of_birth', 'sex', 'contact_number'] as $field) {
                if (self::blank($client->{$field})) {
                    self::fail('source_client_id', 'The existing client profile is incomplete.');
                }
            }
            if (! $client->addresses->contains(fn ($address) => self::hasCompleteAddress($address->toArray()))) {
                self::fail('source_client_id', 'The existing client address is incomplete.');
            }
        }

        if ($this->clientType() === 'NEXT_OF_KIN' && empty($this->selectedNokId())) {
            self::fail('selected_nok_id', 'A selected next of kin is required before publishing.');
        }

        if ($this->clientSource() === 'NEW' && $this->clientType() === 'NEXT_OF_KIN') {
            $selectedNok = collect($data['next_of_kin'] ?? [])
                ->firstWhere('id', $this->selectedNokId());

            if (! is_array($selectedNok)) {
                self::fail('selected_nok_id', 'Selected next of kin must belong to the draft payload.');
            }

            foreach (['first_name', 'last_name', 'relationship', 'phone_number', 'email'] as $field) {
                if (self::blank($selectedNok[$field] ?? null)) {
                    self::fail('next_of_kin.'.$field, 'This field is required for the selected next of kin.');
                }
            }
        }

        $recipientEmail = null;
        if ($this->clientType() === 'OFW') {
            $recipientEmail = $this->clientSource() === 'NEW'
                ? ($data['client']['email'] ?? null)
                : Client::whereKey($this->sourceClientId())->where('is_deleted', false)->value('email');
        } elseif ($this->selectedNokId()) {
            if ($this->clientSource() === 'NEW') {
                foreach ($data['next_of_kin'] ?? [] as $nok) {
                    if (($nok['id'] ?? null) === $this->selectedNokId()) {
                        $recipientEmail = $nok['email'] ?? null;
                        break;
                    }
                }
            } else {
                $recipientEmail = Client::whereKey($this->sourceClientId())
                    ->where('is_deleted', false)->first()?->nextOfKin()
                    ->whereKey($this->selectedNokId())->where('is_deleted', false)->value('email');
            }
        }
        if (self::blank($recipientEmail)) {
            self::fail('email', 'A recipient email address is required before publishing.');
        }
        self::assertCanonicalRecipientEmail($recipientEmail);

        return $this;
    }

    public function toArray(): array
    {
        return $this->values;
    }

    public function clientSource(): string
    {
        return $this->values['client_source'];
    }

    public function sourceClientId(): ?string
    {
        return $this->values['source_client_id'] ?? null;
    }

    public function clientType(): ?string
    {
        return $this->values['client_type'] ?? null;
    }

    public function categoryIds(): array
    {
        return $this->values['category_ids'] ?? [];
    }

    public function nextOfKin(): array
    {
        return $this->values['next_of_kin'] ?? [];
    }

    public function selectedNokId(): ?string
    {
        return $this->values['selected_nok_id'] ?? null;
    }

    public function client(): array
    {
        return $this->values['client'] ?? [];
    }

    public function address(): array
    {
        return $this->values['address'] ?? [];
    }

    public function employment(): array
    {
        return $this->values['employment'] ?? [];
    }

    public function summary(): ?string
    {
        return $this->values['summary'] ?? null;
    }

    public function vulnerabilityIndicator(): ?string
    {
        return $this->values['vulnerability_indicator'] ?? null;
    }

    public function nokVulnerabilityIndicator(): ?string
    {
        return $this->values['nok_vulnerability_indicator'] ?? null;
    }

    public function caseIssueId(): ?string
    {
        return $this->values['case_issue_id'] ?? null;
    }

    public function consentAcceptedAt(): ?string
    {
        return $this->values['consent']['accepted_at'] ?? null;
    }

    public function consentNoticeVersion(): ?string
    {
        return $this->values['consent']['notice_version'] ?? null;
    }

    private static function normalize(array $payload): array
    {
        $payload = array_intersect_key($payload, array_flip(self::TOP_LEVEL));
        $source = $payload['client_source'] ?? null;
        if ($source === 'EXISTING') {
            foreach (['client', 'address', 'employment', 'next_of_kin'] as $field) {
                if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                    self::fail($field, 'Existing-client drafts may only contain canonical client references.');
                }
            }
            unset($payload['client']);
            unset($payload['address'], $payload['employment'], $payload['next_of_kin']);
        } elseif (isset($payload['client']) && is_array($payload['client'])) {
            $payload['client'] = array_intersect_key($payload['client'], array_flip(self::CLIENT_FIELDS));
        }
        foreach (['address' => self::ADDRESS_FIELDS, 'employment' => self::EMPLOYMENT_FIELDS] as $key => $fields) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload[$key] = array_intersect_key($payload[$key], array_flip($fields));
            }
        }
        if (isset($payload['next_of_kin']) && is_array($payload['next_of_kin'])) {
            $payload['next_of_kin'] = array_map(
                fn ($nok) => is_array($nok) ? array_intersect_key($nok, array_flip(self::NOK_FIELDS)) : $nok,
                $payload['next_of_kin'],
            );
        }
        if (isset($payload['consent']) && is_array($payload['consent'])) {
            $payload['consent'] = array_intersect_key($payload['consent'], array_flip(['accepted_at', 'notice_version']));
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            self::fail('payload', 'Draft payload exceeds the maximum size.');
        }

        return $payload;
    }

    private static function assertClient(mixed $client, string $source): void
    {
        if ($source === 'EXISTING') {
            return;
        }
        if ($client !== null && ! is_array($client)) {
            self::fail('client', 'Client data must be an object.');
        }
        if ($client === null) {
            return;
        }
        foreach ($client as $field => $value) {
            if ($field === 'date_of_birth') {
                self::assertDate($value, 'client.'.$field);
            } elseif ($field === 'contact_number') {
                self::assertStringValue($value, 'client.'.$field, 50);
            } elseif ($field === 'email') {
                self::assertEmail($value, 'client.email');
            } elseif ($field === 'sex') {
                self::assertEnum($value, 'client.sex', ['MALE', 'FEMALE']);
            } else {
                self::assertStringValue($value, 'client.'.$field, $field === 'middle_initial' ? self::MIDDLE_INITIAL_MAX_LENGTH : 255);
            }
        }
    }

    private static function assertAddress(mixed $address): void
    {
        if ($address === null) {
            return;
        }
        if (! is_array($address)) {
            self::fail('address', 'Address must be an object.');
        }
        foreach ($address as $field => $value) {
            self::assertStringValue($value, 'address.'.$field, $field === 'street' ? 1000 : 255);
        }
    }

    private static function assertEmployment(mixed $employment): void
    {
        if ($employment === null) {
            return;
        }
        if (! is_array($employment)) {
            self::fail('employment', 'Employment must be an object.');
        }
        foreach ($employment as $field => $value) {
            if (in_array($field, ['start_date', 'end_date', 'date_of_arrival'], true)) {
                self::assertDate($value, 'employment.'.$field);
            } else {
                self::assertStringValue($value, 'employment.'.$field, 255);
            }
        }
    }

    private static function assertNoks(mixed $noks, string $source): void
    {
        if (! is_array($noks) || count($noks) > self::MAX_NOKS) {
            self::fail('next_of_kin', 'Next-of-kin collection is invalid or too large.');
        }
        $ids = [];
        foreach ($noks as $nok) {
            if (! is_array($nok)) {
                self::fail('next_of_kin', 'Each next-of-kin entry must be an object.');
            }
            if ($source === 'NEW' && empty($nok['id'])) {
                self::fail('next_of_kin.id', 'New next-of-kin entries require temporary UUIDs.');
            }
            if (isset($nok['id'])) {
                self::assertUuid($nok['id'], 'next_of_kin.id');
                $ids[] = $nok['id'];
            }
            foreach ($nok as $field => $value) {
                if ($field === 'is_primary') {
                    if (! is_bool($value)) {
                        self::fail('next_of_kin.is_primary', 'Must be a boolean.');
                    }

                    continue;
                }
                if ($field === 'sort_order') {
                    if ($value !== null && (! is_int($value) || $value < 0 || $value > self::POSTGRES_INT4_MAX)) {
                        self::fail('next_of_kin.sort_order', 'Must be a PostgreSQL int4 non-negative integer.');
                    }

                    continue;
                }
                if ($field === 'email') {
                    self::assertEmail($value, 'next_of_kin.email');

                    continue;
                }
                self::assertStringValue($value, 'next_of_kin.'.$field,
                    $field === 'middle_initial' ? self::MIDDLE_INITIAL_MAX_LENGTH
                        : ($field === 'phone_number' ? self::PHONE_MAX_LENGTH
                            : ($field === 'full_address' || $field === 'street' ? 1000 : 255)));
            }
        }
        if (count($ids) !== count(array_unique($ids))) {
            self::fail('next_of_kin', 'Next-of-kin IDs must be distinct.');
        }
    }

    private static function assertConsent(mixed $consent): void
    {
        if ($consent === null) {
            return;
        }
        if (! is_array($consent)) {
            self::fail('consent', 'Consent must be an object.');
        }
        if (isset($consent['accepted_at'])) {
            self::assertTimestamp($consent['accepted_at'], 'consent.accepted_at');
        }
        if (isset($consent['notice_version'])) {
            self::assertNoticeVersion($consent['notice_version']);
        }
        if (array_key_exists('accepted_at', $consent) xor array_key_exists('notice_version', $consent)) {
            self::fail('consent', 'Consent requires timestamp and notice version together.');
        }
    }

    private static function assertUuidList(mixed $values, string $field, int $max, bool $distinct): void
    {
        if (! is_array($values) || count($values) > $max) {
            self::fail($field, 'Collection is invalid or too large.');
        }
        foreach ($values as $value) {
            self::assertUuid($value, $field);
        }
        if ($distinct && count($values) !== count(array_unique($values))) {
            self::fail($field, 'IDs must be distinct.');
        }
    }

    private static function assertUuidIfPresent(array $payload, string $field): void
    {
        if (isset($payload[$field])) {
            self::assertUuid($payload[$field], $field);
        }
    }

    private static function assertUuid(mixed $value, string $field): void
    {
        if (! is_string($value) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            self::fail($field, 'Must be a UUID.');
        }
    }

    private static function assertString(array $payload, string $field, int $max): void
    {
        if (array_key_exists($field, $payload)) {
            self::assertStringValue($payload[$field], $field, $max);
        }
    }

    private static function assertStringValue(mixed $value, string $field, int $max): void
    {
        if ($value !== null && (! is_string($value) || mb_strlen($value) > $max)) {
            self::fail($field, 'Invalid or too long.');
        }
    }

    private static function assertDate(mixed $value, string $field): void
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($value !== null && (! $date || $date->format('Y-m-d') !== $value)) {
            self::fail($field, 'Must be a valid date.');
        }
    }

    private static function assertTimestamp(mixed $value, string $field): void
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) : false;
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            self::fail($field, 'Must be a valid ISO-8601 timestamp.');
        }
        if ($date > new DateTimeImmutable('now')) {
            self::fail($field, 'Cannot be in the future.');
        }
    }

    private static function assertEmail(mixed $value, string $field): void
    {
        if ($value === null) {
            return;
        }
        if (! is_string($value) || mb_strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            self::fail($field, 'Must be a valid email address.');
        }
    }

    private static function assertEnum(mixed $value, string $field, array $allowed): void
    {
        if ($value === null) {
            return;
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            self::fail($field, 'Contains an unsupported value.');
        }
    }

    private static function assertNoticeVersion(mixed $value): void
    {
        if (! is_string($value) || ! in_array($value, self::APPROVED_NOTICE_VERSIONS, true)) {
            self::fail('consent.notice_version', 'Must be a valid notice version.');
        }
    }

    private static function assertPublishDbLimits(array $data): void
    {
        $client = $data['client'] ?? [];
        self::assertStringValue($client['middle_initial'] ?? null, 'client.middle_initial', self::MIDDLE_INITIAL_MAX_LENGTH);
        self::assertStringValue($client['contact_number'] ?? null, 'client.contact_number', self::PHONE_MAX_LENGTH);

        foreach ($data['next_of_kin'] ?? [] as $index => $nok) {
            self::assertStringValue($nok['middle_initial'] ?? null, "next_of_kin.{$index}.middle_initial", self::MIDDLE_INITIAL_MAX_LENGTH);
            self::assertStringValue($nok['phone_number'] ?? null, "next_of_kin.{$index}.phone_number", self::PHONE_MAX_LENGTH);
        }
    }

    private static function assertCanonicalRecipientEmail(mixed $value): void
    {
        if (! is_string($value) || $value !== trim($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            self::fail('email', 'Must be a valid canonical recipient email address.');
        }
    }

    private static function hasCompleteAddress(array $address): bool
    {
        foreach (['region', 'province', 'city_municipality', 'barangay'] as $field) {
            if (self::blank($address[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
