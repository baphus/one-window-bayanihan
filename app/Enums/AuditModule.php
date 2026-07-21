<?php

namespace App\Enums;

use App\Services\AuditCategory;

/**
 * Canonical audit "module" (the subject area a log entry belongs to).
 *
 * Single source of truth for three things that used to be duplicated across
 * AuditLogController::moduleAliases(), AuditLogFormatter::formatModule() and
 * AuditCategory's private module lists:
 *   1. legacy/alias normalisation  -> tryFromLegacy()
 *   2. human display label         -> label()
 *   3. default event category      -> category()
 *
 * Like AuditAction, this is deliberately NOT an Eloquent cast on AuditLog:
 * module participates in the frozen chainDigest() and the column stores many
 * historical legacy spellings. Writers should emit the canonical ->value.
 */
enum AuditModule: string
{
    // Business data
    case CASE = 'case';
    case CLIENT = 'client';
    case CLIENT_ADDRESS = 'client_address';
    case CLIENT_EMPLOYMENT = 'client_employment';
    case REFERRAL = 'referral';
    case MILESTONE = 'milestone';
    case REFERRAL_ATTACHMENT = 'referral_attachment';
    case REFERRAL_CLIENT_REQUEST = 'referral_client_request';
    case REFERRAL_CLIENT_REQUEST_ITEM = 'referral_client_request_item';
    case REFERRAL_CLIENT_MESSAGE = 'referral_client_message';
    case REFERRAL_CLIENT_ACCESS_LINK = 'referral_client_access_link';
    case FEEDBACK = 'feedback';
    case NEXT_OF_KIN = 'next_of_kin';

    // Configuration & administration
    case USER = 'user';
    case AGENCY = 'agency';
    case SERVICE = 'service';
    case SERVICE_REQUIREMENT = 'service_requirement';
    case CASE_CATEGORY = 'case_category';
    case CASE_ISSUE = 'case_issue';
    case CASE_STATUS = 'case_status';
    case HELPDESK_ARTICLE = 'helpdesk_article';
    case AUDIT = 'audit';
    case DATA_EXPORT = 'data_export';
    case EMAIL_LOG = 'email_log';
    case SYSTEM_SETTING = 'system_setting';

    // Account security surfaces
    case AUTH = 'auth';
    case SECURITY = 'security';
    case SESSION = 'session';
    case MFA = 'mfa';

    /**
     * Legacy/alias spellings found in stored rows and older call sites, mapped
     * to their canonical case. Keys are lower-cased before lookup.
     */
    private const ALIASES = [
        'cases' => self::CASE,
        'case_files' => self::CASE,
        // Historical mixed-case spellings that still exist in stored rows.
        // tryFromLegacy() lower-cases input before lookup, so these keys are
        // matched only when enumerating aliases() for the module filter.
        'CASE' => self::CASE,
        'REFERRAL' => self::REFERRAL,
        'clients' => self::CLIENT,
        'client_addresses' => self::CLIENT_ADDRESS,
        'client_employments' => self::CLIENT_EMPLOYMENT,
        'referrals' => self::REFERRAL,
        'milestones' => self::MILESTONE,
        'referral_attachments' => self::REFERRAL_ATTACHMENT,
        'feedbacks' => self::FEEDBACK,
        'next_of_kins' => self::NEXT_OF_KIN,
        'users' => self::USER,
        'agencies' => self::AGENCY,
        'services' => self::SERVICE,
        'service_requirements' => self::SERVICE_REQUIREMENT,
        'case_categories' => self::CASE_CATEGORY,
        'case_issues' => self::CASE_ISSUE,
        'case_statuses' => self::CASE_STATUS,
        'helpdesk_articles' => self::HELPDESK_ARTICLE,
        'email_logs' => self::EMAIL_LOG,
        'system_settings' => self::SYSTEM_SETTING,
        // Legacy AdminUserController spelling for an email change.
        'email' => self::USER,
    ];

    /**
     * Resolve any stored/legacy module string (case-insensitive, singular or
     * plural, canonical or alias) to its enum case, or null if unrecognised.
     */
    public static function tryFromLegacy(string $module): ?self
    {
        $normalized = strtolower(trim($module));

        return self::tryFrom($normalized) ?? self::ALIASES[$normalized] ?? null;
    }

    /**
     * Every stored spelling (canonical + aliases) that maps to this case.
     * Used by the audit-log module filter to match legacy data.
     */
    public function aliases(): array
    {
        $spellings = [$this->value];
        foreach (self::ALIASES as $spelling => $case) {
            if ($case === $this) {
                $spellings[] = $spelling;
            }
        }

        return array_values(array_unique($spellings));
    }

    /** Human-readable display label. */
    public function label(): string
    {
        return match ($this) {
            self::CASE => 'Case',
            self::CLIENT => 'Client',
            self::CLIENT_ADDRESS => 'Address',
            self::CLIENT_EMPLOYMENT => 'Employment Record',
            self::REFERRAL => 'Referral',
            self::MILESTONE => 'Milestone',
            self::REFERRAL_ATTACHMENT => 'Attachment',
            self::AGENCY => 'Agency',
            self::USER => 'User',
            self::SERVICE => 'Service',
            self::HELPDESK_ARTICLE => 'Helpdesk Article',
            self::MFA => 'MFA',
            self::AUTH => 'Authentication',
            default => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    /** Default event category for this module (see AuditCategory). */
    public function category(): string
    {
        return match ($this) {
            self::AUTH, self::SECURITY, self::SESSION, self::MFA => AuditCategory::SECURITY,
            self::USER, self::AGENCY, self::SERVICE, self::SERVICE_REQUIREMENT,
            self::CASE_CATEGORY, self::CASE_ISSUE, self::CASE_STATUS,
            self::HELPDESK_ARTICLE, self::AUDIT, self::DATA_EXPORT,
            self::EMAIL_LOG, self::SYSTEM_SETTING => AuditCategory::ADMIN,
            default => AuditCategory::DATA,
        };
    }

    /** True for account-security surfaces regardless of action. */
    public function isSecurity(): bool
    {
        return in_array($this, [self::AUTH, self::SECURITY, self::SESSION, self::MFA], true);
    }
}
