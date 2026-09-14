<?php

namespace Database\Seeders\Staging;

/**
 * Per-table volume targets for the 6-month staging dataset (plan §4:
 * ~250 cases/month → ~1,500 cases, ~100k rows total).
 *
 * Targets are approximate ("~" in the plan) — the seeding phases use them
 * as exact loop bounds and reconcile documented ratios (e.g. referrals land
 * at ~1.8/case via DRAFT×0 / others×2, SERVQUAL at exactly 22/feedback).
 * Structural rates stated in the plan live here too, with derived-total
 * helpers so later phases never hardcode arithmetic.
 */
class VolumeModel
{
    // ------------------------------------------------------------------
    // People & cases
    // ------------------------------------------------------------------

    /** ~1,500 case owners + ~300 extra (draft-only / no case yet). */
    public const CLIENTS = 1800;

    public const CLIENT_ADDRESSES = 1800;

    /** Base count: 1 per client; see SECOND_EMPLOYMENT_RATE. */
    public const CLIENT_EMPLOYMENTS = 1800;

    /** ~10% of clients record a 2nd employment (job change). */
    public const SECOND_EMPLOYMENT_RATE = 0.10;

    /** ~2,200 with the 2nd-NOK rate applied (1800 × 1.2 = 2160 ≈ 2200). */
    public const NEXT_OF_KIN = 2200;

    /** ~20% of clients get a 2nd NOK. */
    public const SECOND_NOK_RATE = 0.20;

    /** 250/month × 6 months. */
    public const CASES = 1500;

    /** 80% of cases carry a case_category pivot row. */
    public const CASE_CATEGORY_PIVOT = 1200;

    public const CASE_CATEGORY_RATE = 0.80;

    /** ~1.7/case avg; more on COMPLETED cases. */
    public const CASE_DOCUMENTS = 2500;

    /** ~3/case avg; email-type notifications. */
    public const CASE_NOTIFICATIONS = 4500;

    // ------------------------------------------------------------------
    // Referral flow
    // ------------------------------------------------------------------

    /** ~1.8/case (DRAFT 0, others 2). */
    public const REFERRALS = 2700;

    /** Status-driven counts (plan §5.4 templates). */
    public const MILESTONES = 7000;

    /** ~1.5/referral, incl. replies. */
    public const REFERRAL_COMMENTS = 4000;

    /** ~0.75/referral; some versioned via replaces_id. */
    public const REFERRAL_ATTACHMENTS = 2000;

    /**
     * 2/referral avg (service requirements). Lives in
     * referral_service_requirements — the old referral_compliance_requirements
     * table was dropped by migration 2026_07_18_000001.
     */
    public const COMPLIANCE_REQUIREMENTS = 5400;

    // ------------------------------------------------------------------
    // Client request flow
    // ------------------------------------------------------------------

    /** ~0.5/referral on PROCESSING/FOR_COMPLIANCE. */
    public const CLIENT_REQUESTS = 1300;

    /** 2/request avg. */
    public const CLIENT_REQUEST_ITEMS = 2600;

    /** ~3/request avg (agency + client sides). */
    public const CLIENT_MESSAGES = 3900;

    /** 1/request. */
    public const CLIENT_ACCESS_LINKS = 1300;

    // ------------------------------------------------------------------
    // Feedback + surveys
    // ------------------------------------------------------------------

    /** ~20% of COMPLETED referrals. */
    public const FEEDBACK = 540;

    /** Exactly 22/feedback (540 × 22 = 11,880 ≈ ~12,000). */
    public const SERVQUAL_RESPONSES = 12000;

    public const SERVQUAL_PER_FEEDBACK = 22;

    /** 1 per agency (10) + 1 DMW. */
    public const SURVEY_FORMS = 11;

    /** ~10/form. */
    public const SURVEY_QUESTIONS = 110;

    /** ~5/COMPLETED referral (post-feedback). */
    public const SURVEY_INVITATIONS = 2700;

    /** ~70% invitation response rate. */
    public const SURVEY_RESPONSES = 1900;

    public const SURVEY_RESPONSE_RATE = 0.70;

    // ------------------------------------------------------------------
    // System-generated rows
    // ------------------------------------------------------------------

    /** Mirrors notifications + invitations + client messages. */
    public const EMAIL_LOGS = 6000;

    /** Event-driven per case timeline (plan §8). */
    public const AUDIT_MIN = 30000;

    public const AUDIT_MAX = 50000;

    // ------------------------------------------------------------------
    // Derived totals & config view
    // ------------------------------------------------------------------

    /**
     * Total employment rows including 2nd-employment (job-change) rows.
     */
    public static function clientEmploymentsTotal(): int
    {
        return (int) round(self::CLIENT_EMPLOYMENTS * (1 + self::SECOND_EMPLOYMENT_RATE));
    }

    /**
     * Total NOK rows including 2nd-NOK rows.
     */
    public static function nextOfKinTotal(): int
    {
        return (int) round(self::CLIENTS * (1 + self::SECOND_NOK_RATE));
    }

    /**
     * [min, max] expected audit rows.
     *
     * @return array{int, int}
     */
    public static function auditRange(): array
    {
        return [self::AUDIT_MIN, self::AUDIT_MAX];
    }

    /**
     * Per-table target counts as a config array (table => rows), including
     * derived employment/NOK totals.
     *
     * @return array<string, int>
     */
    public static function all(): array
    {
        return [
            'clients' => self::CLIENTS,
            'client_addresses' => self::CLIENT_ADDRESSES,
            'client_employments' => self::clientEmploymentsTotal(),
            'next_of_kin' => self::nextOfKinTotal(),
            'cases' => self::CASES,
            'case_category' => self::CASE_CATEGORY_PIVOT,
            'case_documents' => self::CASE_DOCUMENTS,
            'case_notifications' => self::CASE_NOTIFICATIONS,
            'referrals' => self::REFERRALS,
            'milestones' => self::MILESTONES,
            'referral_comments' => self::REFERRAL_COMMENTS,
            'referral_attachments' => self::REFERRAL_ATTACHMENTS,
            'referral_service_requirements' => self::COMPLIANCE_REQUIREMENTS,
            'referral_client_request' => self::CLIENT_REQUESTS,
            'referral_client_request_item' => self::CLIENT_REQUEST_ITEMS,
            'referral_client_message' => self::CLIENT_MESSAGES,
            'referral_client_access_link' => self::CLIENT_ACCESS_LINKS,
            'feedback' => self::FEEDBACK,
            'feedback_servqual_responses' => self::SERVQUAL_RESPONSES,
            'survey_forms' => self::SURVEY_FORMS,
            'survey_questions' => self::SURVEY_QUESTIONS,
            'survey_invitations' => self::SURVEY_INVITATIONS,
            'survey_responses' => self::SURVEY_RESPONSES,
            'email_logs' => self::EMAIL_LOGS,
        ];
    }

    /**
     * Estimated non-audit insert rows (~100k per the plan).
     */
    public static function estimatedTotalRows(): int
    {
        return array_sum(self::all());
    }
}
