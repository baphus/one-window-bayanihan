<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Referral;
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
        'agcy_id' => [Agency::class, 'name'],
        'refr_id' => [Referral::class, 'required_services'],
        'milestone_id' => [Milestone::class, 'title'],
        'case_id' => [CaseFile::class, 'case_number'],
    ];

    /**
     * Free-text fields are excluded from the safe audit response: their raw
     * stored value passes through formatFieldValue() unchanged, so surfacing
     * them would re-disclose legacy/free text the guardrail forbids.
     */
    private const FREE_TEXT_FIELDS = [
        'client',
        'notes',
        'summary',
        'description',
        'content',
        'draft_client_data',
        'decision_comment',
        'address',
        'street',
        'city_municipality',
        'province',
        'barangay',
        'region',
        'employer_name',
        'last_country',
        'last_position',
        'position',
        'comments',
    ];

    /**
     * Fields whose raw (unformatted) value is still safe to surface on the
     * audit page even though formatFieldValue() leaves it unchanged.
     */
    private const CONTROLLED_FIELDS = [
        'decision',
        'case_number',
        'tracker_number',
        'date_of_birth',
        'sex',
        'title',
        'client_type',
        'case_issue',
        'next_of_kin_count',
        'category_names',
    ];

    public function format(AuditLog $log): string
    {
        if ($log->description !== null) {
            return $log->description;
        }

        $userName = $this->resolveUserName($log);
        $module = $this->formatModule((string) $log->module);
        $action = strtoupper((string) $log->action);

        // Purposeful verbs render as the target sentence:
        //   {Actor name} ({Role label}) {verb past-tense} {Entity identifier} {— salient detail}
        // The role label is resolved from the causer's stored role; the
        // entity identifier and salient detail come from entity_label +
        // old_value/new_value.
        return match ($action) {
            'LOGIN' => $this->formatLogin($userName, $log),
            'LOGOUT' => $this->formatLogout($userName),
            'CREATE' => $this->formatCreate($userName, $log, $module),
            'UPDATE' => $this->formatUpdate($log, $module),
            'DELETE' => $this->formatDelete($userName, $log, $module),
            'PUBLISH' => $this->formatPublish($userName, $log, $module),
            // Auth & security
            'ACCEPT_INVITE' => $this->formatPurposefulSimple($userName, $log, 'accepted a staff invitation'),
            'REQUEST_PASSWORD_RESET' => $this->formatPurposefulSimple($userName, $log, 'requested a password reset link'),
            'RESET_PASSWORD' => $this->formatPurposefulSimple($userName, $log, 'reset their password'),
            'CHANGE_PASSWORD' => $this->formatPurposefulSimple($userName, $log, 'changed their password'),
            'VERIFY_EMAIL' => $this->formatPurposefulSimple($userName, $log, 'verified their email address'),
            'CHANGE_EMAIL' => $this->formatEmailChange($userName, $log, false),
            'ENABLE_MFA' => $this->formatPurposefulSimple($userName, $log, 'enabled two-factor authentication'),
            'DISABLE_MFA' => $this->formatPurposefulSimple($userName, $log, 'disabled two-factor authentication'),
            'REGENERATE_RECOVERY_CODES' => $this->formatPurposefulSimple($userName, $log, 'regenerated MFA recovery codes'),
            'ADMIN_RESET_PASSWORD' => $this->formatAdminAction($userName, $log, 'reset the password of'),
            'ADMIN_CHANGE_EMAIL' => $this->formatEmailChange($userName, $log, true),
            'ADMIN_VERIFY_EMAIL' => $this->formatAdminAction($userName, $log, 'verified the email of'),
            'DELETE_ACCOUNT' => $this->formatPurposefulSimple($userName, $log, 'deleted their own account'),
            'PURGE_USER' => $this->formatAdminAction($userName, $log, 'permanently deleted'),
            'REVOKE_SESSIONS' => $this->formatPurposefulSimple($userName, $log, 'revoked MFA-enrolled sessions'),
            // Case Manager
            'APPROVE' => $this->formatCaseDecision($userName, $log, 'approved'),
            'REJECT' => $this->formatReject($userName, $log),
            'SEND' => $this->formatSend($userName, $log),
            'CLOSE' => $this->formatCaseDecision($userName, $log, 'closed'),
            'CREATE_CASE_ISSUE' => $this->formatCreateCaseIssue($userName, $log),
            'DOWNLOAD' => $this->formatDownload($userName, $log),
            'EXPORT' => $this->formatExport($userName, $log),
            // Agency Focal referral lifecycle
            'ACCEPT' => $this->formatReferralDecision($userName, $log, 'accepted'),
            'REQUEST_COMPLIANCE' => $this->formatReferralDecision($userName, $log, 'requested compliance for'),
            'RESUME' => $this->formatReferralDecision($userName, $log, 'resumed'),
            'COMPLETE' => $this->formatReferralDecision($userName, $log, 'completed'),
            'ASSIGN_SERVICE' => $this->formatServiceAction($userName, $log, 'assigned a service to'),
            'UNASSIGN_SERVICE' => $this->formatServiceAction($userName, $log, 'removed a service from'),
            'ADD_REQUIREMENT' => $this->formatRequirementAction($userName, $log, 'added a requirement to'),
            'UPDATE_REQUIREMENT' => $this->formatRequirementAction($userName, $log, 'updated a requirement on'),
            'REMOVE_REQUIREMENT' => $this->formatRequirementAction($userName, $log, 'removed a requirement from'),
            'ISSUE_CLIENT_REQUEST' => $this->formatClientRequestAction($userName, $log, 'issued a client request on'),
            'ISSUE_LINK' => $this->formatLinkAction($userName, $log, 'issued an access link for'),
            'REISSUE_LINK' => $this->formatLinkAction($userName, $log, 'reissued an access link for'),
            'REVOKE_LINK' => $this->formatLinkAction($userName, $log, 'revoked an access link for'),
            'RESOLVE_CLIENT_REQUEST' => $this->formatClientRequestAction($userName, $log, 'resolved a client request on'),
            'CANCEL_CLIENT_REQUEST' => $this->formatClientRequestAction($userName, $log, 'cancelled a client request on'),
            'REOPEN_CLIENT_REQUEST' => $this->formatClientRequestAction($userName, $log, 'reopened a client request on'),
            // System Admin
            'INVITE' => $this->formatInviteAction($userName, $log, 'sent an invitation to'),
            'RESEND_INVITE' => $this->formatInviteAction($userName, $log, 'resent an invitation to'),
            'CANCEL_INVITE' => $this->formatInviteAction($userName, $log, 'cancelled an invitation to'),
            'DEACTIVATE' => $this->formatAdminAction($userName, $log, 'deactivated'),
            'REACTIVATE' => $this->formatAdminAction($userName, $log, 'reactivated'),
            'RESET_MFA' => $this->formatAdminAction($userName, $log, 'reset MFA for'),
            'TOGGLE_MAINTENANCE' => $this->formatToggleMaintenance($userName, $log),
            'UPDATE_SECURITY' => $this->formatPurposefulSimple($userName, $log, 'updated security settings'),
            'SEND_REMINDER' => $this->formatSendReminder($userName, $log),
            // OFW / public
            'REGISTER' => $this->formatRegister($userName, $log),
            'FILE_INTAKE' => $this->formatFileIntake($userName, $log),
            'REPLY_CLIENT_REQUEST' => $this->formatPurposefulSimple($userName, $log, 'replied to a client request'),
            'UPLOAD_REPLY_DOC' => $this->formatPurposefulSimple($userName, $log, 'uploaded a reply document'),
            'TRACK_CASE' => $this->formatTrackCase($userName, $log),
            'SUBMIT_SURVEY' => $this->formatPurposefulSimple($userName, $log, 'submitted a satisfaction survey'),
            'SUBMIT_CONTACT' => $this->formatPurposefulSimple($userName, $log, 'submitted a contact request'),
            default => sprintf('%s %s %s', $userName, $this->formatAction($action), $module),
        };
    }

    /**
     * Resolve the actor's role label for the target sentence format.
     * Agency Focal entries include the agency name: `(Agency Focal, OWWA)`.
     * Anonymous (public-link) actors resolve to `Anonymous (link holder)`.
     */
    private function resolveRoleLabel(AuditLog $log): ?string
    {
        $user = $log->relationLoaded('user') ? $log->getRelation('user') : null;

        if ($user === null && $log->user_id !== null) {
            $user = cache()->remember(
                "audit_actor:{$log->user_id}",
                now()->addMinutes(10),
                fn () => User::find($log->user_id),
            );
        }

        if ($user === null) {
            // Unattributed entry from a public link flow.
            return $log->user_id === null ? 'link holder' : null;
        }

        return match ($user->role) {
            UserRole::CASE_MANAGER->value => 'Case Manager',
            UserRole::AGENCY->value => $user->agency?->name !== null
                ? "Agency Focal, {$user->agency->name}"
                : 'Agency Focal',
            UserRole::ADMIN->value => 'System Admin',
            default => null,
        };
    }

    /**
     * Build the actor + role suffix: `Juan dela Cruz (Case Manager)`.
     * Anonymous actors (null user_id on a public-link action) resolve to
     * `Anonymous (link holder)`.
     */
    private function resolveActorWithRole(AuditLog $log): string
    {
        if ($log->user_id === null) {
            return 'Anonymous (link holder)';
        }

        $name = $this->resolveUserName($log);
        $role = $this->resolveRoleLabel($log);

        return $role !== null ? "{$name} ({$role})" : $name;
    }

    /**
     * Resolve the entity identifier for the target sentence from the
     * entity_label snapshot, falling back to new_value/old_value fields.
     */
    private function resolvePurposefulEntity(AuditLog $log): ?string
    {
        if (is_string($log->entity_label) && $log->entity_label !== '') {
            return $log->entity_label;
        }

        $new = is_array($log->new_value) ? $log->new_value : [];
        $old = is_array($log->old_value) ? $log->old_value : [];
        $all = array_merge($old, $new);

        return $this->extractEntityDetail($all, (string) $log->module) ?: null;
    }

    /**
     * Render a salient detail suffix from new_value/old_value keys that carry
     * the "why" of a business decision (reason, service, email, count, etc.).
     */
    private function resolveSalientDetail(AuditLog $log, array $keys): ?string
    {
        $new = is_array($log->new_value) ? $log->new_value : [];
        $old = is_array($log->old_value) ? $log->old_value : [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $new) && is_scalar($new[$key]) && (string) $new[$key] !== '') {
                return (string) $new[$key];
            }
            if (array_key_exists($key, $old) && is_scalar($old[$key]) && (string) $old[$key] !== '') {
                return (string) $old[$key];
            }
        }

        return null;
    }

    /**
     * Simple purposeful verb: `{Actor} ({Role}) {verb phrase}`.
     * Used when the entity is the actor themselves (password, MFA, account).
     */
    private function formatPurposefulSimple(string $userName, AuditLog $log, string $verbPhrase): string
    {
        $actor = $this->resolveActorWithRole($log);

        return "{$actor} {$verbPhrase}";
    }

    /**
     * Admin acting on another user: `{Actor} ({Role}) {verb} {Target name}`.
     */
    private function formatAdminAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $target = $this->resolvePurposefulEntity($log) ?? $this->resolveSalientDetail($log, ['name', 'email']);

        return $target !== null
            ? "{$actor} {$verb} {$target}"
            : "{$actor} {$verb} a user";
    }

    /**
     * Email change (self or admin-driven): includes old → new addresses.
     */
    private function formatEmailChange(string $userName, AuditLog $log, bool $admin): string
    {
        $actor = $this->resolveActorWithRole($log);
        $old = $this->resolveSalientDetail($log, ['old_email']) ?? ($log->old_value['email'] ?? null);
        $new = $this->resolveSalientDetail($log, ['new_email']) ?? ($log->new_value['email'] ?? null);
        $verb = $admin ? 'changed the email of' : 'changed their email';

        if ($admin) {
            $target = $this->resolvePurposefulEntity($log);
            $base = $target !== null
                ? "{$actor} {$verb} {$target}"
                : "{$actor} {$verb} a user";
        } else {
            $base = "{$actor} {$verb}";
        }

        if ($old !== null && $new !== null) {
            return "{$base} from {$old} to {$new}";
        }

        return $base;
    }

    /**
     * Reject dispatches by module: case rejections surface the deletion
     * reason; referral rejections surface the rejection reason.
     */
    private function formatReject(string $userName, AuditLog $log): string
    {
        $moduleRaw = strtolower((string) $log->module);

        if (in_array($moduleRaw, ['referral', 'referrals'], true)) {
            return $this->formatReferralDecision($userName, $log, 'rejected');
        }

        return $this->formatCaseDecision($userName, $log, 'rejected');
    }

    /**
     * Case Manager decision on a case: `{Actor} ({Role}) {verb} Case {number} {— detail}`.
     */
    private function formatCaseDecision(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $caseNumber = $this->resolvePurposefulEntity($log)
            ?? $this->resolveSalientDetail($log, ['case_number']);

        $base = $caseNumber !== null
            ? "{$actor} {$verb} Case {$caseNumber}"
            : "{$actor} {$verb} a case";

        $detail = $this->resolveSalientDetail($log, ['reason', 'deletion_reason', 'summary']);

        return $detail !== null ? "{$base} — {$detail}" : $base;
    }

    /**
     * Sending a referral to an agency: includes agency name + attachment count.
     */
    private function formatSend(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);
        $agency = $this->resolveSalientDetail($log, ['agency_name']);
        $attachmentCount = $log->new_value['attachment_count'] ?? null;

        $base = $entity !== null
            ? "{$actor} sent {$entity}"
            : "{$actor} sent a referral";

        if ($agency !== null) {
            $base .= " to {$agency}";
        }

        if ($attachmentCount !== null) {
            $base .= " — {$attachmentCount} attachment(s)";
        }

        // Re-refer context
        $previousId = $log->new_value['previous_referral_id'] ?? null;
        $rejectionReason = $log->new_value['rejection_reason'] ?? null;

        if ($previousId !== null) {
            $base .= ' (re-referral)';
        }

        if ($rejectionReason !== null) {
            $base .= " — previous rejection: {$rejectionReason}";
        }

        return $base;
    }

    /**
     * Quick-add case issue during casework.
     */
    private function formatCreateCaseIssue(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $issueName = $this->resolvePurposefulEntity($log)
            ?? $this->resolveSalientDetail($log, ['case_issue', 'name']);

        return $issueName !== null
            ? "{$actor} added case issue {$issueName}"
            : "{$actor} added a case issue";
    }

    /**
     * Download of a document or attachment.
     */
    private function formatDownload(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $fileName = $this->resolvePurposefulEntity($log)
            ?? $this->resolveSalientDetail($log, ['file_name']);

        return $fileName !== null
            ? "{$actor} downloaded {$fileName}"
            : "{$actor} downloaded a document";
    }

    /**
     * Export of cases/clients/referrals/audit/reports.
     */
    private function formatExport(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $format = $this->resolveSalientDetail($log, ['format']);
        $scope = $this->resolveSalientDetail($log, ['scope', 'type']);

        $base = "{$actor} exported";

        if ($scope !== null) {
            $base .= " {$scope}";
        }

        if ($format !== null) {
            $base .= " ({$format})";
        }

        return $base;
    }

    /**
     * Agency Focal referral decision: includes service + agency in entity label.
     */
    private function formatReferralDecision(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);

        $base = $entity !== null
            ? "{$actor} {$verb} {$entity}"
            : "{$actor} {$verb} a referral";

        $reason = $this->resolveSalientDetail($log, ['rejection_reason', 'decision_comment']);

        return $reason !== null ? "{$base} — {$reason}" : $base;
    }

    /**
     * Service assignment/unassignment on a referral.
     */
    private function formatServiceAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);
        $serviceName = $this->resolveSalientDetail($log, ['service_name']);

        $base = $entity !== null
            ? "{$actor} {$verb} {$entity}"
            : "{$actor} {$verb} a referral";

        if ($serviceName !== null) {
            $base .= " — {$serviceName}";
        }

        return $base;
    }

    /**
     * Requirement management on a referral service.
     */
    private function formatRequirementAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);
        $requirement = $this->resolveSalientDetail($log, ['requirement_name', 'title']);

        $base = $entity !== null
            ? "{$actor} {$verb} {$entity}"
            : "{$actor} {$verb} a referral";

        if ($requirement !== null) {
            $base .= " — {$requirement}";
        }

        return $base;
    }

    /**
     * Client request lifecycle action on a referral.
     */
    private function formatClientRequestAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);

        return $entity !== null
            ? "{$actor} {$verb} {$entity}"
            : "{$actor} {$verb} a referral";
    }

    /**
     * Access link lifecycle.
     */
    private function formatLinkAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $entity = $this->resolvePurposefulEntity($log);

        return $entity !== null
            ? "{$actor} {$verb} {$entity}"
            : "{$actor} {$verb} a referral";
    }

    /**
     * Invitation lifecycle: target email is the salient detail.
     */
    private function formatInviteAction(string $userName, AuditLog $log, string $verb): string
    {
        $actor = $this->resolveActorWithRole($log);
        $email = $this->resolveSalientDetail($log, ['email']);

        return $email !== null
            ? "{$actor} {$verb} {$email}"
            : "{$actor} {$verb} a user";
    }

    /**
     * Maintenance mode toggle: enabled/disabled from new_value.
     */
    private function formatToggleMaintenance(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $enabled = $log->new_value['enabled'] ?? null;
        $verb = $enabled ? 'enabled maintenance mode' : 'disabled maintenance mode';

        return "{$actor} {$verb}";
    }

    /**
     * Overdue reminder batch.
     */
    private function formatSendReminder(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $count = $log->new_value['reminder_count'] ?? $log->new_value['count'] ?? null;

        return $count !== null
            ? "{$actor} sent overdue reminders to {$count} referral(s)"
            : "{$actor} sent overdue referral reminders";
    }

    /**
     * OFW self-registration.
     */
    private function formatRegister(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $email = $this->resolveSalientDetail($log, ['email']);

        return $email !== null
            ? "{$actor} registered an account ({$email})"
            : "{$actor} registered an account";
    }

    /**
     * OFW self-filed intake.
     */
    private function formatFileIntake(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $tracker = $this->resolvePurposefulEntity($log)
            ?? $this->resolveSalientDetail($log, ['tracker_number', 'case_number']);

        return $tracker !== null
            ? "{$actor} filed an intake ({$tracker})"
            : "{$actor} filed an intake";
    }

    /**
     * Case tracking via OTP.
     */
    private function formatTrackCase(string $userName, AuditLog $log): string
    {
        $actor = $this->resolveActorWithRole($log);
        $tracker = $this->resolvePurposefulEntity($log)
            ?? $this->resolveSalientDetail($log, ['tracker_number', 'case_number']);

        return $tracker !== null
            ? "{$actor} tracked Case {$tracker}"
            : "{$actor} tracked a case";
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
            'middle_name' => 'middle initial',
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
            'last_position' => 'last occupation',
            'country' => 'country',
            'position' => 'occupation',
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
            'case_issue' => 'case issue',
            'category_id' => 'category',
            'category_names' => 'categories',
            'next_of_kin_count' => 'next of kin count',
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

        // Summarize the PUBLISH client snapshot — show the full name instead of
        // a raw field count. Sensitive snapshot keys (contact number, birth
        // date, sex) stay in the JSONB evidence and are not surfaced here.
        if ($field === 'client' && is_array($value)) {
            $nameParts = array_filter([
                $value['first_name'] ?? '',
                $value['middle_name'] ?? '',
                $value['last_name'] ?? '',
                $value['suffix'] ?? '',
            ], fn ($part) => $part !== '' && $part !== null);

            $name = trim(implode(' ', $nameParts));

            return $name !== '' ? $name : sprintf('%d fields', count($value));
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
                UserRole::CASE_MANAGER->value => 'Case Manager',
                UserRole::AGENCY->value => 'Agency Focal',
                UserRole::ADMIN->value => 'System Admin',
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

    /**
     * Safe response contract for every browser and export audit surface.
     *
     * Audit records are evidence, not presentation objects: historic rows can
     * contain free text and legacy values that are no longer safe to disclose.
     * Never add raw attributes, stored descriptions, or value diffs here. The
     * later classified-event policy may opt individual, approved summaries back
     * in; until then this deliberately exposes only event metadata.
     *
     * The message appends a resolved entity identifier (case number, or a
     * short UUID for referrals/milestones) when that is safe to disclose, and
     * `changes` carries an after-only payload of fields whose formatted value
     * is a controlled transformation (free-text fields are never surfaced).
     *
     * @return array{
     *     id: string,
     *     action: string,
     *     module: string,
     *     formatted_module: string,
     *     category: string|null,
     *     message: string,
     *     detail: string,
     *     changes: array<int, array{field:string, fieldLabel:string, new:string}|array{field:string, fieldLabel:string, old:string|null, new:string|null}>,
     *     actor: string,
     *     timestamp: string|null,
     *     hasChanges: bool
     * }
     */
    public function formatForAuditResponse(AuditLog $log, bool $isAdmin = false): array
    {
        $action = AuditAction::tryFrom(strtoupper((string) $log->action))?->value ?? 'UNKNOWN';
        $actor = $this->resolveUserName($log);
        $auditModule = AuditModule::tryFromLegacy((string) $log->module);
        $module = $auditModule?->value ?? 'other';
        $moduleLabel = $auditModule?->label() ?? 'Other activity';
        $category = in_array($log->category, AuditCategory::ALL, true) ? $log->category : null;
        $classifiable = $auditModule !== null && $action !== 'UNKNOWN';

        $identifier = $classifiable ? $this->resolveEntityIdentifier($log, $auditModule) : null;
        $message = $this->formatSafeMessage($actor, $action, $moduleLabel, $identifier);

        if ($isAdmin && $classifiable && in_array($action, ['PUBLISH', 'CREATE'], true)) {
            $message = $this->formatAdminEnrichedMessage($message, $actor, $action, $log->new_value);
        }

        return [
            'id' => (string) $log->getKey(),
            'action' => $action,
            'module' => $module,
            'formatted_module' => $moduleLabel,
            'category' => $category,
            'message' => $message,
            'detail' => '',
            'changes' => $changes = ($classifiable
                ? ($isAdmin
                    ? $this->adminChanges($log, $action)
                    : $this->getSafeAfterChanges($log, $action))
                : []),
            'actor' => $actor,
            'timestamp' => $log->timestamp?->toISOString(),
            'hasChanges' => $changes !== [],
        ];
    }

    /**
     * Admin before/after diff, guarded by the same rule as
     * getSafeAfterChanges(): a value formatFieldValue() cannot transform is
     * free text (case notes, comments, client snapshots on the loose end) and
     * never reaches the API. Structured snapshots — the client cart, resolved
     * names, CONTROLLED_FIELDS — still surface in full.
     *
     * @return array<int, array{field:string, fieldLabel:string, old:string|null, new:string|null}>
     */
    private function adminChanges(AuditLog $log, string $action): array
    {
        $sides = [
            is_array($log->old_value) ? $log->old_value : [],
            is_array($log->new_value) ? $log->new_value : [],
        ];

        return array_values(array_filter(
            $this->getStructuredChanges($log->old_value, $log->new_value, $action),
            function (array $change) use ($sides): bool {
                $field = (string) $change['field'];

                foreach ($sides as $side) {
                    if (! array_key_exists($field, $side)) {
                        continue;
                    }

                    $value = $side[$field];
                    $formatted = $this->formatFieldValue('', $field, $value);
                    $raw = is_scalar($value) ? (string) $value : json_encode($value);

                    if ($formatted === $raw && ! in_array($field, self::CONTROLLED_FIELDS, true)) {
                        // Raw value passed through unchanged — treat as unsafe free text.
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Admin-only PUBLISH/CREATE message: prefer the enriched new_value payload
     * (case_number + client snapshot + category_names) over the generic safe
     * message. FREE_TEXT_FIELDS are never surfaced here.
     */
    private function formatAdminEnrichedMessage(string $fallback, string $actor, string $action, mixed $newValue): string
    {
        if (! is_array($newValue) || $newValue === []) {
            return $fallback;
        }

        $clientName = null;
        if (isset($newValue['client']) && is_array($newValue['client'])) {
            $formatted = $this->formatFieldValue('', 'client', $newValue['client']);
            if (! str_ends_with($formatted, 'fields') && $formatted !== 'empty') {
                $clientName = $formatted;
            }
        } elseif (isset($newValue['first_name']) || isset($newValue['last_name'])) {
            $full = trim(($newValue['first_name'] ?? '').' '.($newValue['last_name'] ?? ''));
            if ($full !== '') {
                $clientName = $full;
            }
        }

        $categories = null;
        if (isset($newValue['category_names']) && is_array($newValue['category_names'])) {
            $names = array_values(array_filter(
                $newValue['category_names'],
                fn ($v) => is_scalar($v) && (string) $v !== ''
            ));
            if ($names !== []) {
                $categories = implode(', ', array_map('strval', $names));
            }
        } elseif (isset($newValue['case_issue']) && is_string($newValue['case_issue']) && $newValue['case_issue'] !== '') {
            $categories = $newValue['case_issue'];
        }

        $caseNumber = isset($newValue['case_number']) && is_string($newValue['case_number']) && $newValue['case_number'] !== ''
            ? $newValue['case_number']
            : null;

        // Without any enriched parts there is nothing to prefer over the safe message.
        if ($caseNumber === null && $clientName === null && $categories === null) {
            return $fallback;
        }

        $base = $caseNumber !== null
            ? sprintf('%s %s Case %s', $actor, $this->formatAction($action), $caseNumber)
            : $fallback;

        if ($clientName !== null) {
            $base .= sprintf(' for %s', $clientName);
        }

        if ($categories !== null) {
            $base .= sprintf(' — %s', $categories);
        }

        return $base;
    }

    /**
     * Resolve a safe display identifier for the affected entity, derived from
     * the stored entity_id (never echoed back as a raw UUID into the message).
     */
    private function resolveEntityIdentifier(AuditLog $log, AuditModule $module): ?string
    {
        $entityId = $log->entity_id;

        if (! is_string($entityId) || $entityId === '' || ! Str::isUuid($entityId)) {
            return null;
        }

        // Prefer the durable entity_label snapshot when present.
        $label = is_string($log->entity_label ?? null) && $log->entity_label !== ''
            ? $log->entity_label
            : null;

        return match ($module) {
            AuditModule::CASE => $label !== null
                ? "Case {$label}"
                : $this->resolveCaseNumber($entityId),
            AuditModule::REFERRAL => $label !== null
                ? "Referral — {$label}"
                : $this->resolveReferralLabel($entityId),
            AuditModule::MILESTONE => $label !== null
                ? "Milestone '{$label}'"
                : $this->resolveMilestoneTitle($entityId),
            default => null,
        };
    }

    private function resolveCaseNumber(string $caseId): ?string
    {
        // Case numbers are assigned once at creation, but invalidate on any
        // CaseFile write (see CacheInvalidationObserver::invalidateCase) so a
        // correction can never linger behind this cache.
        $number = cache()->remember(
            "audit_case_number:{$caseId}",
            now()->addMinutes(10),
            fn () => CaseFile::query()->whereKey($caseId)->value('case_number'),
        );

        return is_string($number) && $number !== '' ? "Case {$number}" : null;
    }

    private function resolveReferralLabel(string $referralId): ?string
    {
        $label = cache()->remember("audit_referral_label:{$referralId}", now()->addHour(), function () use ($referralId) {
            $referral = Referral::query()->with('agency')->find($referralId);

            if ($referral === null) {
                return null;
            }

            $serviceName = $referral->services()->pluck('name')->implode(', ');

            $label = $serviceName !== '' ? $serviceName : ($referral->required_services ?: null);

            if ($label !== null && $referral->agency !== null) {
                $label .= " ({$referral->agency->name})";
            }

            return $label;
        });

        return is_string($label) && $label !== '' ? "Referral — {$label}" : 'Referral ID '.substr($referralId, 0, 8);
    }

    private function resolveMilestoneTitle(string $milestoneId): ?string
    {
        $title = cache()->remember(
            "audit_milestone_title:{$milestoneId}",
            now()->addHour(),
            fn () => Milestone::query()->whereKey($milestoneId)->value('title'),
        );

        return is_string($title) && $title !== ''
            ? "Milestone '{$title}'"
            : 'Milestone ID '.substr($milestoneId, 0, 8);
    }

    /**
     * After-only safe changes for the audit response.
     *
     * Only fields whose formatted value is a controlled transformation may
     * surface: raw free text passes through formatFieldValue() unchanged and is
     * dropped, while mapped statuses, booleans, roles, resolved names, list
     * summaries and a small allow-list of controlled fields are kept.
     *
     * @return array<int, array{field:string, fieldLabel:string, new:string}>
     */
    private function getSafeAfterChanges(AuditLog $log, string $action): array
    {
        $new = $log->new_value;

        if (! is_array($new) || $new === []) {
            return [];
        }

        $excludeFields = array_merge(
            $this->noiseFields,
            $action === 'CREATE' ? $this->createNoiseFields : [],
            $action === 'PUBLISH' ? ['category_ids'] : [],
            self::FREE_TEXT_FIELDS,
        );

        $changes = [];

        foreach ($new as $field => $value) {
            if (in_array($field, $excludeFields, true)) {
                continue;
            }

            $formatted = $this->formatFieldValue('', (string) $field, $value);
            $raw = is_scalar($value) ? (string) $value : json_encode($value);

            if (! in_array($field, self::CONTROLLED_FIELDS, true) && $formatted === $raw) {
                // Raw value passed through unchanged — treat as unsafe free text.
                continue;
            }

            $changes[] = [
                'field' => $field,
                'fieldLabel' => $this->formatFieldName((string) $field),
                'new' => $formatted,
            ];
        }

        return $changes;
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

        if (in_array($moduleRaw, ['client', 'clients'])) {
            $name = $this->extractEntityDetail($newValues, $moduleRaw);

            return $name ? sprintf('%s was added as a client', $name) : 'A new client was added';
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

    private function formatSafeMessage(string $actor, string $action, string $module, ?string $identifier = null): string
    {
        return match ($action) {
            'LOGIN' => sprintf('%s signed in', $actor),
            'LOGOUT' => sprintf('%s signed out', $actor),
            'LOGIN_FAILED' => 'A sign-in attempt failed',
            'EXPORT' => sprintf('%s requested an audit log export', $actor),
            'UNKNOWN' => 'An unclassified activity was recorded',
            default => sprintf('%s %s %s', $actor, $this->formatAction($action), $identifier ?? strtolower($module)),
        };
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
