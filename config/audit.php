<?php

use App\Models\Agency;
use App\Models\CaseCategory;
use App\Models\CaseFile;
use App\Models\CaseIssue;
use App\Models\CaseStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientEmployment;
use App\Models\Milestone;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralClientAccessLink;
use App\Models\ReferralClientMessage;
use App\Models\ReferralClientRequest;
use App\Models\ReferralClientRequestItem;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Hot retention window (days)
    |--------------------------------------------------------------------------
    | Audit entries younger than this stay queryable in Postgres. Older
    | entries are archived to immutable bundles by audit:archive and then
    | removed by audit:prune (which refuses rows not yet archived).
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Archive storage
    |--------------------------------------------------------------------------
    | Filesystem disk receiving monthly NDJSON bundles + manifests.
    | Production must configure this disk as S3-compatible object storage
    | (see config/filesystems.php 'audit-archives').
    */
    'archive_disk' => env('AUDIT_ARCHIVE_DISK', 'audit-archives'),

    /*
    |--------------------------------------------------------------------------
    | Chain verification baseline
    |--------------------------------------------------------------------------
    | Rows written before the advisory-lock chain fix deployed can contain
    | forks from the historical race condition. Chain breaks at rows older
    | than this timestamp are reported as accepted anomalies instead of
    | failing audit:verify. Set to the deployment timestamp of the fix;
    | leave null for strict verification of the entire table.
    */
    'chain_verified_from' => env('AUDIT_CHAIN_VERIFIED_FROM'),

    /*
    |--------------------------------------------------------------------------
    | Export limits
    |--------------------------------------------------------------------------
    | The audit CSV export requires an explicit date range: the UI defaults
    | to `default_days`, the range may not exceed the hot retention window,
    | and result sets above `max_rows` are rejected before streaming.
    */
    'export' => [
        'default_days' => 30,
        'max_rows' => (int) env('AUDIT_EXPORT_MAX_ROWS', 100000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Observed models
    |--------------------------------------------------------------------------
    | Eloquent models whose create/update/delete/restore events are recorded by
    | App\Observers\AuditObserver. Single source of truth: AppServiceProvider
    | registers the observer from this list, and AuditModelCoverageTest asserts
    | every entry actually emits an audit row — so removing a model here (and
    | thus silently dropping its audit trail) fails a test.
    */
    'observed_models' => [
        CaseFile::class,
        Client::class,
        ClientAddress::class,
        ClientEmployment::class,
        Referral::class,
        Milestone::class,
        ReferralAttachment::class,
        Agency::class,
        User::class,
        Service::class,
        ServiceRequirement::class,
        CaseCategory::class,
        CaseIssue::class,
        CaseStatus::class,
        ReferralClientRequest::class,
        ReferralClientRequestItem::class,
        ReferralClientMessage::class,
        ReferralClientAccessLink::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive-value redaction policy
    |--------------------------------------------------------------------------
    | Single source of truth for the fields whose values are scrubbed to
    | '[REDACTED]' before an audit row is persisted. Applied recursively to
    | old_value/new_value by AuditLog::saving(), so it covers every write path
    | (observer, listeners, manual creates) and any nesting depth.
    |
    | This is defence in depth: most models already drop credential/PII columns
    | via their $auditExclude list before data reaches the log. This net catches
    | anything that slips through (ad-hoc payloads, nested arrays, future
    | fields) so secrets never land in the immutable, exportable audit trail.
    |
    |   keys     - exact field names (compared case-insensitively).
    |   patterns - substrings; any field whose lower-cased name contains one is
    |              redacted (e.g. 'token' catches access_token, refresh_token).
    */
    'redact' => [
        'keys' => [
            'password',
            'remember_token',
            'mfa_secret',
            'mfa_recovery_codes',
            'mfa_enabled_at',
            'otp',
            'otp_code',
            'csrf',
            'signature',
            'session_id',
            'jwt',
            'salt',
            'pin',
            'passphrase',
        ],
        'patterns' => [
            'password',
            'passwd',
            'secret',
            'token',
            'authorization',
            'bearer',
            'cookie',
            'credential',
            'api_key',
            'apikey',
            'private_key',
        ],
    ],

];
