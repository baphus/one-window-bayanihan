<?php

namespace App\Services;

use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogFormatter
{
    private array $noiseFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'email_verified_at', 'timestamp'];

    private array $createNoiseFields = ['case_number', 'tracker_number', 'consent_given_at', 'status'];

    private array $uuidFieldMap = [
        'user_id' => [User::class, 'name'],
        'category_id' => [CaseCategory::class, 'name'],
        'case_issue_id' => [CaseIssue::class, 'name'],
        'client_id' => [Client::class, 'first_name', 'last_name'],
    ];

    public function format(AuditLog $log): string
    {
        if ($log->description !== null) {
            return $log->description;
        }

        $userName = $this->resolveUserName($log);
        $module = $this->formatModule((string) $log->module);
        $action = strtoupper((string) $log->action);

        return match ($action) {
            'LOGIN' => $this->formatLogin($userName, $log),
            'LOGOUT' => $this->formatLogout($userName),
            'CREATE' => $this->formatCreate($userName, $log, $module),
            'UPDATE' => $this->formatUpdate($log, $module),
            'DELETE' => $this->formatDelete($userName, $log, $module),
            'PUBLISH' => $this->formatPublish($userName, $log, $module),
            default => sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
        };
    }

    public function formatAction(string $action): string
    {
        return match (strtoupper($action)) {
            'CREATE' => 'created',
            'UPDATE' => 'updated',
            'DELETE' => 'deleted',
            'LOGIN' => 'signed in',
            'LOGOUT' => 'signed out',
            'PUBLISH' => 'published',
            'ARCHIVE' => 'archived',
            'UNARCHIVE' => 'unarchived',
            default => strtolower($action),
        };
    }

    public function formatModule(string $module): string
    {
        // Module identity, aliases and labels are centralised on AuditModule.
        // Unrecognised modules fall back to a Title-Cased spelling.
        return AuditModule::tryFromLegacy($module)?->label()
            ?? ucwords(str_replace('_', ' ', strtolower($module)));
    }

    public function formatFieldName(string $field): string
    {
        return match ($field) {
            'case_number' => 'case number',
            'tracker_number' => 'tracker number',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'middle_initial' => 'middle initial',
            'name' => 'name',
            'email' => 'email address',
            'role' => 'user role',
            'status' => 'status',
            'required_services' => 'service type',
            'description' => 'description',
            'title' => 'title',
            'summary' => 'summary',
            'notes' => 'notes',
            'is_deleted' => 'deleted status',
            'is_active' => 'active status',
            'is_verified' => 'verified status',
            'deleted_at' => 'deletion date',
            'agcy_id' => 'agency',
            'user_id' => 'assigned user',
            'client_type' => 'client type',
            'sex' => 'gender',
            'date_of_birth' => 'birth date',
            'phone_number' => 'phone number',
            'address' => 'address',
            'last_country' => 'last country of work',
            'last_position' => 'last position',
            'country' => 'country',
            'position' => 'position',
            'street' => 'street address',
            'city_municipality' => 'city/municipality',
            'province' => 'province',
            'barangay' => 'barangay',
            'region' => 'region',
            'password' => 'password',
            'remember_token' => 'remember token',
            'vulnerability_indicator' => 'vulnerability level',
            'nok_vulnerability_indicator' => 'NOK vulnerability level',
            'consent_given_at' => 'consent date',
            'escalation_reason' => 'escalation reason',
            'case_issue_id' => 'case issue',
            'category_id' => 'category',
            default => str_replace('_', ' ', $field),
        };
    }

    public function formatFieldValue(string $module, string $field, mixed $value): string
    {
        if ($value === null) {
            return 'not set';
        }

        if (is_bool($value) || $value === 1 || $value === 0 || $value === '1' || $value === '0') {
            return in_array($value, [true, 1, '1'], true) ? 'Yes' : 'No';
        }

        // Summarize draft_client_data — extract key info instead of showing raw JSON
        // Must be checked BEFORE generic array handling
        if ($field === 'draft_client_data' && is_array($value)) {
            $first = $value['first_name'] ?? '';
            $last = $value['last_name'] ?? '';
            $name = trim("$first $last");
            $email = $value['email'] ?? '';
            $clientType = $value['client_type'] ?? '';

            $parts = [];
            if ($name !== '') {
                $parts[] = $name;
            }
            if ($clientType !== '') {
                $parts[] = "($clientType)";
            }
            if ($email !== '') {
                $parts[] = "— $email";
            }

            return $parts !== [] ? 'Client: '.implode(' ', $parts) : sprintf('%d fields', count($value));
        }

        if (is_array($value)) {
            $count = count($value);
            if ($count === 0) {
                return 'empty';
            }
            // Check if it's a flat list (sequential integer keys)
            if (array_is_list($value)) {
                $items = array_map(fn ($v) => is_scalar($v) ? (string) $v : '…', array_slice($value, 0, 3));
                $suffix = $count > 3 ? sprintf(' (+%d more)', $count - 3) : '';

                return implode(', ', $items).$suffix;
            }

            // Associative array — show key count
            return sprintf('%d fields', $count);
        }

        $stringValue = (string) $value;
        $normalized = strtoupper($stringValue);

        $statusMap = [
            'OPEN' => 'Open',
            'CLOSED' => 'Closed',
            'DRAFT' => 'Draft',
            'ARCHIVED' => 'Archived',
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'COMPLETED' => 'Completed',
            'REJECTED' => 'Rejected',
            'FOR_COMPLIANCE' => 'For Compliance',
        ];

        if (isset($statusMap[$normalized])) {
            return $statusMap[$normalized];
        }

        if ($field === 'role') {
            return match ($normalized) {
                'CASE_MANAGER' => 'Case Manager',
                'AGENCY' => 'Agency Focal',
                'ADMIN' => 'System Admin',
                default => $stringValue,
            };
        }

        if ($field === 'client_type') {
            return match ($normalized) {
                'OFW' => 'OFW',
                'NEXT_OF_KIN' => 'Next of Kin',
                default => $stringValue,
            };
        }

        // Resolve UUID foreign keys to human-readable names
        $resolved = $this->resolveUuidValue($field, $value);
        if ($resolved !== null) {
            return $resolved;
        }

        return $stringValue;
    }

    /**
     * Stable display contract consumed by every frontend audit surface
     * (resources/js/lib/audit.jsx and the three renderers). Controllers attach
     * these keys onto each row; the shape must not drift without updating the
     * JS side:
     *   message    string  human-readable description
     *   detail     string  reserved secondary line (currently empty)
     *   changes    array   [{ field, fieldLabel, old, new }]
     *   action     string  raw AuditAction value (e.g. "UPDATE")
     *   module     string  human module label (e.g. "Case")
     *   actor      string  actor name, or "System"
     *   timestamp  string  ISO-8601
     *   hasChanges bool
     */
    public function formatForDisplay(AuditLog $log): array
    {
        $description = $this->format($log);
        $userName = $this->resolveUserName($log);
        $action = strtoupper((string) $log->action);
        $changes = $this->getStructuredChanges($log->old_value, $log->new_value, $action);

        return [
            'message' => $description,
            'detail' => '',
            'changes' => $changes,
            'action' => $action,
            'module' => $this->formatModule((string) $log->module),
            'actor' => $userName,
            'timestamp' => $log->timestamp?->toISOString(),
            'hasChanges' => $changes !== [],
        ];
    }

    public function getStructuredChanges(?array $old, ?array $new, string $action = 'UPDATE'): array
    {
        if ($old === null && $new === null) {
            return [];
        }

        $excludeFields = $action === 'CREATE'
            ? array_merge($this->noiseFields, $this->createNoiseFields)
            : $this->noiseFields;

        $changes = [];

        if ($old === null && is_array($new)) {
            // CREATE: all fields are new
            foreach ($new as $field => $value) {
                if (in_array($field, $excludeFields, true)) {
                    continue;
                }
                $changes[] = [
                    'field' => $field,
                    'fieldLabel' => $this->formatFieldName((string) $field),
                    'old' => null,
                    'new' => $this->formatFieldValue('', (string) $field, $value),
                ];
            }
        } elseif ($new === null && is_array($old)) {
            // DELETE: all fields were removed
            foreach ($old as $field => $value) {
                if (in_array($field, $excludeFields, true)) {
                    continue;
                }
                $changes[] = [
                    'field' => $field,
                    'fieldLabel' => $this->formatFieldName((string) $field),
                    'old' => $this->formatFieldValue('', (string) $field, $value),
                    'new' => null,
                ];
            }
        } elseif (is_array($old) && is_array($new)) {
            // UPDATE: compare old vs new
            $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
            foreach ($fields as $field) {
                if (in_array($field, $excludeFields, true)) {
                    continue;
                }
                $oldValue = array_key_exists($field, $old) ? $old[$field] : null;
                $newValue = array_key_exists($field, $new) ? $new[$field] : null;

                if ($oldValue == $newValue) {
                    continue;
                }

                $changes[] = [
                    'field' => $field,
                    'fieldLabel' => $this->formatFieldName((string) $field),
                    'old' => $this->formatFieldValue('', (string) $field, $oldValue),
                    'new' => $this->formatFieldValue('', (string) $field, $newValue),
                ];
            }
        }

        return $changes;
    }

    private function resolveUuidValue(string $field, mixed $value): ?string
    {
        if (! isset($this->uuidFieldMap[$field]) || ! is_string($value)) {
            return null;
        }

        // Quick check: UUIDs are 36 chars with 4 hyphens
        if (strlen($value) !== 36 || substr_count($value, '-') !== 4) {
            return null;
        }

        $nameFields = $this->uuidFieldMap[$field];
        $modelClass = array_shift($nameFields);
        $model = $modelClass::find($value);

        if ($model === null) {
            return null;
        }

        // Combine name fields (e.g. first_name + last_name for Client)
        $parts = array_map(fn ($f) => $model->{$f} ?? '', $nameFields);
        $name = trim(implode(' ', $parts));

        return $name !== '' ? $name : null;
    }

    private function formatLogin(string $userName, AuditLog $log): string
    {
        $timezone = $log->relationLoaded('user') && $log->getRelation('user')?->timezone
            ? $log->getRelation('user')->timezone
            : 'Asia/Manila';

        $time = $log->timestamp?->setTimezone($timezone)->format('g:i A');

        // Add user email for better identification
        $userLabel = $userName;
        if ($log->relationLoaded('user') && $log->getRelation('user') !== null) {
            $email = $log->getRelation('user')->email ?? null;
            if ($email) {
                $userLabel = sprintf('%s (%s)', $userName, $email);
            }
        }

        return $time
            ? sprintf('%s signed in on %s at %s', $userLabel, $log->timestamp?->setTimezone($timezone)->format('F j, Y'), $time)
            : sprintf('%s signed in', $userLabel);
    }

    private function formatLogout(string $userName): string
    {
        return sprintf('%s signed out', $userName);
    }

    private function formatCreate(string $userName, AuditLog $log, string $module): string
    {
        $newValues = $log->new_value ?? [];
        $moduleRaw = strtolower((string) $log->module);

        // Specific template: User creation
        if (in_array($moduleRaw, ['users', 'user'])) {
            $name = $newValues['name'] ?? $newValues['first_name'] ?? null;
            $role = isset($newValues['role']) ? $this->formatFieldValue($moduleRaw, 'role', $newValues['role']) : null;
            if ($name && $role) {
                return sprintf('%s registered as %s', $name, $role);
            }
            if ($name) {
                return sprintf('%s was registered', $name);
            }
        }

        // Specific template: Case creation
        if (in_array($moduleRaw, ['case_files', 'case'])) {
            $caseNumber = $newValues['case_number'] ?? null;
            $clientType = isset($newValues['client_type']) ? $this->formatFieldValue($moduleRaw, 'client_type', $newValues['client_type']) : null;
            if ($caseNumber && $clientType) {
                return sprintf('Case %s opened for %s client', $caseNumber, $clientType);
            }
            if ($caseNumber) {
                return sprintf('Case %s opened', $caseNumber);
            }
        }

        // Specific template: Referral creation
        if (in_array($moduleRaw, ['referrals', 'referral'])) {
            $serviceType = $newValues['required_services'] ?? null;
            $identifier = $this->extractEntityDetail($newValues, $moduleRaw);
            $base = $identifier ? sprintf('Referral %s created', $identifier) : 'Referral created';

            return $serviceType ? sprintf('%s — %s', $base, $serviceType) : $base;
        }

        // Generic CREATE
        $identifier = $this->extractEntityDetail($newValues, $moduleRaw);
        if ($identifier) {
            return sprintf('%s was added to %s', $identifier, strtolower($module));
        }
        if ($userName === 'System') {
            return sprintf('A new %s was created', strtolower($module));
        }

        return sprintf('%s created a new %s', $userName, strtolower($module));
    }

    private function formatUpdate(AuditLog $log, string $module): string
    {
        $moduleRaw = (string) $log->module;
        $oldValues = $log->old_value ?? [];
        $newValues = $log->new_value ?? [];
        $allValues = array_merge($oldValues, $newValues);

        // Extract the entity identifier (case number, name, etc.)
        $identifier = $this->extractEntityDetail($allValues, $moduleRaw);
        $entityPrefix = $identifier ?: $module;

        $changes = $this->getStructuredChanges($log->old_value, $log->new_value, 'UPDATE');

        if (empty($changes)) {
            return sprintf('%s was updated', $entityPrefix);
        }

        if (count($changes) === 1) {
            $change = $changes[0];

            return sprintf('%s %s changed to %s', $entityPrefix, $change['fieldLabel'], $change['new'] ?? 'not set');
        }

        // Multi-field: name the changed fields (up to 3) instead of
        // collapsing to "first field +N more"
        $labels = array_map(fn ($c) => $c['fieldLabel'], array_slice($changes, 0, 3));
        $suffix = count($changes) > 3 ? sprintf(' and %d more field%s', count($changes) - 3, count($changes) - 3 === 1 ? '' : 's') : '';

        return sprintf('%s updated: %s%s', $entityPrefix, implode(', ', $labels), $suffix);
    }

    private function formatDelete(string $userName, AuditLog $log, string $module): string
    {
        $oldValues = $log->old_value ?? [];
        $identifier = $this->extractEntityDetail($oldValues, strtolower((string) $log->module));

        if ($identifier) {
            return sprintf('%s was removed from the system', $identifier);
        }

        if ($userName === 'System') {
            return sprintf('A %s was removed', strtolower($module));
        }

        return sprintf('%s removed a %s', $userName, strtolower($module));
    }

    private function formatPublish(string $userName, AuditLog $log, string $module): string
    {
        $newValues = $log->new_value ?? [];
        $moduleRaw = (string) $log->module;

        if (in_array($moduleRaw, ['CASE', 'case_files', 'case'])) {
            $caseNumber = $newValues['case_number'] ?? null;
            $trackerNumber = $newValues['tracker_number'] ?? null;
            $summary = $newValues['summary'] ?? null;

            if ($caseNumber) {
                $base = sprintf('%s published Case %s', $userName, $caseNumber);
            } elseif ($trackerNumber) {
                $base = sprintf('%s published Case %s', $userName, $trackerNumber);
            } else {
                $base = sprintf('%s published a case', $userName);
            }

            if (! empty($summary) && $summary !== 'not set') {
                $base .= sprintf(' — %s', $summary);
            }

            return $base;
        }

        // Generic publish
        $identifier = $this->extractEntityDetail($newValues, $moduleRaw);
        if ($identifier) {
            return sprintf('%s published %s', $userName, $identifier);
        }

        return sprintf('%s published %s', $userName, strtolower($module));
    }

    private function resolveUserName(AuditLog $log): string
    {
        // Prefer the eager-loaded relation; fall back to a cached lookup so
        // human actions are never mislabeled "System" just because a caller
        // didn't eager-load the user.
        $user = $log->relationLoaded('user') ? $log->getRelation('user') : null;
        $name = $user?->name;

        if ($name === null && $log->user_id !== null && Str::isUuid((string) $log->user_id)) {
            $name = cache()->remember(
                "audit_actor_name:{$log->user_id}",
                now()->addMinutes(10),
                fn () => User::find($log->user_id)?->name ?? ''
            );
        }

        return is_string($name) && $name !== '' ? $name : 'System';
    }

    private function extractEntityDetail(?array $values, string $module): string
    {
        if ($values === null) {
            return '';
        }

        // Combine first_name + last_name as a single identifier
        if (array_key_exists('first_name', $values) || array_key_exists('last_name', $values)) {
            $first = $values['first_name'] ?? '';
            $last = $values['last_name'] ?? '';
            $fullName = trim("$first $last");
            if ($fullName !== '') {
                return $fullName;
            }
        }

        $identifierFields = [
            'case_number',
            'tracker_number',
            'title',
            'name',
            'summary',
            'description',
            'content',
            'file_name',
        ];

        foreach ($identifierFields as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $value = $this->formatFieldValue($module, $field, $values[$field]);

            if ($value === '' || $value === 'not set') {
                continue;
            }

            if (in_array($field, ['case_number', 'tracker_number'], true)) {
                return $value;
            }

            return $value;
        }

        return '';
    }
}
