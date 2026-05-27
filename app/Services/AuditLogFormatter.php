<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogFormatter
{
    public function format(AuditLog $log): string
    {
        if ($log->description !== null) {
            return $log->description;
        }

        $userName = $this->resolveUserName($log);
        $module = $this->formatModule((string) $log->module);
        $action = strtoupper((string) $log->action);

        return match ($action) {
            'LOGIN' => sprintf('%s %s', $userName, $this->formatAction($action)),
            'LOGOUT' => sprintf('%s %s', $userName, $this->formatAction($action)),
            'VIEW' => sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
            'CREATE' => $this->appendEntityDetails(
                sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
                $log->new_value,
                $module,
            ),
            'UPDATE' => $this->appendChangeDetails(
                sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
                $this->formatChanges($log->old_value, $log->new_value),
            ),
            'DELETE' => $this->appendEntityDetails(
                sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
                $log->old_value,
                $module,
            ),
            default => sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
        };
    }

    public function formatAction(string $action): string
    {
        return match (strtoupper($action)) {
            'CREATE' => 'created',
            'UPDATE' => 'updated',
            'DELETE' => 'deleted',
            'VIEW' => 'viewed',
            'LOGIN' => 'signed in',
            'LOGOUT' => 'signed out',
            default => strtolower($action),
        };
    }

    public function formatModule(string $module): string
    {
        return match ($module) {
            'case_files' => 'Case',
            'clients' => 'Client',
            'client_addresses' => 'Address',
            'client_employments' => 'Employment Record',
            'referrals' => 'Referral',
            'milestones' => 'Milestone',
            'referral_attachments' => 'Attachment',
            'agencies' => 'Agency',
            'users' => 'User',
            'services' => 'Service',
            'helpdesk_articles' => 'Helpdesk Article',
            default => ucfirst(str_replace('_', ' ', $module)),
        };
    }

    public function formatFieldName(string $field): string
    {
        return match ($field) {
            'case_number' => 'case number',
            'tracker_number' => 'tracker number',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'middle_name' => 'middle name',
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
                'OFW' => 'Overseas Filipino Worker',
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
                $fieldName = $this->formatFieldName((string) $field);
                $changes[] = sprintf('set %s to %s', $fieldName, $this->formatFieldValue($module, (string) $field, $value));
            }

            return implode('; ', $changes);
        }

        if ($new === null && is_array($old)) {
            foreach ($old as $field => $value) {
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

    private function appendChangeDetails(string $description, string $changes): string
    {
        return $changes !== '' ? $description.' — '.$changes : $description;
    }

    private function appendEntityDetails(string $description, ?array $values, string $module): string
    {
        $detail = $this->extractEntityDetail($values, $module);

        return $detail !== '' ? $description.' '.$detail : $description;
    }

    private function extractEntityDetail(?array $values, string $module): string
    {
        if ($values === null) {
            return '';
        }

        $identifierFields = [
            'case_number',
            'tracker_number',
            'title',
            'name',
            'summary',
            'description',
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
                return $this->formatModule($module).' #'.$value;
            }

            return $this->formatModule($module).' '.$value;
        }

        return '';
    }
}
