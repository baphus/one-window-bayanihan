<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogFormatter
{
    private array $noiseFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'email_verified_at', 'timestamp'];

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
        $normalized = strtolower($module);

        return match ($normalized) {
            'case_files' => 'Case',
            'cases' => 'Case',
            'case' => 'Case',
            'clients' => 'Client',
            'client' => 'Client',
            'client_addresses' => 'Address',
            'client_address' => 'Address',
            'client_employments' => 'Employment Record',
            'client_employment' => 'Employment Record',
            'referrals' => 'Referral',
            'referral' => 'Referral',
            'milestones' => 'Milestone',
            'milestone' => 'Milestone',
            'referral_attachments' => 'Attachment',
            'referral_attachment' => 'Attachment',
            'agencies' => 'Agency',
            'agency' => 'Agency',
            'users' => 'User',
            'user' => 'User',
            'services' => 'Service',
            'service' => 'Service',
            'helpdesk_articles' => 'Helpdesk Article',
            'helpdesk_article' => 'Helpdesk Article',
            default => ucfirst(str_replace('_', ' ', $normalized)),
        };
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
            'sla_target_days' => 'SLA target days',
            'sla_met' => 'SLA met',
            'escalated_at' => 'escalated at',
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

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'not set';
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

        return $stringValue;
    }

    public function formatChanges(?array $old, ?array $new): string
    {
        if ($old === null && $new === null) {
            return '';
        }

        $module = '';
        $changes = [];

        if ($old === null && is_array($new)) {
            foreach ($new as $field => $value) {
                if (in_array($field, $this->noiseFields, true)) {
                    continue;
                }
                $fieldName = $this->formatFieldName((string) $field);
                $changes[] = sprintf('set %s to %s', $fieldName, $this->formatFieldValue($module, (string) $field, $value));
            }

            return implode('; ', $changes);
        }

        if ($new === null && is_array($old)) {
            foreach ($old as $field => $value) {
                if (in_array($field, $this->noiseFields, true)) {
                    continue;
                }
                $changes[] = sprintf('cleared %s', $this->formatFieldName((string) $field));
            }

            return implode('; ', $changes);
        }

        $old = $old ?? [];
        $new = $new ?? [];
        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($fields as $field) {
            $hasOld = array_key_exists($field, $old);
            $hasNew = array_key_exists($field, $new);
            $oldValue = $hasOld ? $old[$field] : null;
            $newValue = $hasNew ? $new[$field] : null;

            if ($hasOld && $hasNew && $oldValue == $newValue) {
                continue;
            }

            if (in_array($field, $this->noiseFields, true)) {
                continue;
            }

            $fieldName = $this->formatFieldName((string) $field);
            $changes[] = sprintf(
                'changed %s from %s to %s',
                $fieldName,
                $this->formatFieldValue($module, (string) $field, $oldValue),
                $this->formatFieldValue($module, (string) $field, $newValue),
            );
        }

        return implode('; ', $changes);
    }

    public function formatForDisplay(AuditLog $log): array
    {
        $description = $this->format($log);
        $changes = $this->formatChanges($log->old_value, $log->new_value);
        $userName = $this->resolveUserName($log);
        $action = strtoupper((string) $log->action);

        return [
            'message' => $description,
            'detail' => $changes,
            'action' => $action,
            'module' => $this->formatModule((string) $log->module),
            'actor' => $userName,
            'timestamp' => $log->timestamp?->toISOString(),
            'hasChanges' => $changes !== '',
        ];
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

        $changes = $this->formatChanges($log->old_value, $log->new_value);

        if ($changes === '') {
            return sprintf('%s was updated', $entityPrefix);
        }

        // Parse changes to produce cleaner single-field update messages
        $changeParts = explode('; ', $changes);
        if (count($changeParts) === 1) {
            // Single change: "changed status from Processing to Completed"
            $change = $changeParts[0];
            if (preg_match('/^changed (.+) from .+ to (.+)$/', $change, $matches)) {
                return sprintf('%s %s changed to %s', $entityPrefix, $matches[1], $matches[2]);
            }
        }

        // Multi-field: use first change + "and more"
        $firstChange = $changeParts[0];
        if (preg_match('/^changed (.+) from .+ to (.+)$/', $firstChange, $matches)) {
            $suffix = count($changeParts) > 1 ? ' (+'.(count($changeParts) - 1).' more)' : '';

            return sprintf('%s %s changed to %s%s', $entityPrefix, $matches[1], $matches[2], $suffix);
        }

        return sprintf('%s was updated', $entityPrefix);
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
        if (! $log->relationLoaded('user')) {
            return 'System';
        }

        $user = $log->getRelation('user');

        if ($user === null) {
            return 'System';
        }

        $name = $user->name ?? null;

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
