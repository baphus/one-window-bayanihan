<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Fluent writer for purposeful audit entries.
 *
 * Centralises the request-context trio (ip_address, user_agent, request_id),
 * the causer, the entity_label snapshot, and description delegation to
 * {@see AuditLogFormatter}, so every business decision reads as its own verb
 * instead of a generic CREATE/UPDATE/DELETE.
 *
 * The frozen SHA-256 hash chain on `AuditLog` is untouched: this service only
 * assembles the payload and calls `AuditLog::create()`, which still runs the
 * `creating`/`saving` boot hooks that stamp `prev_hash`, `chain_seq`,
 * `category` and redaction.
 *
 * Usage (preferred over raw `AuditLog::create` for every manual business
 * event):
 *
 *   AuditLogger::approve()
 *       ->by($user)
 *       ->forEntity($case, $case->case_number)
 *       ->module(AuditModule::CASE)
 *       ->context(['status' => 'PENDING_REVIEW'], ['status' => 'OPEN'])
 *       ->write();
 *
 * Every method returns `$this` for chaining; `write()` persists and returns
 * the created `AuditLog`.
 */
class AuditLogger
{
    private AuditAction $action;

    private AuditModule $module;

    private ?string $entityId = null;

    private ?string $entityLabel = null;

    private ?array $oldValue = null;

    private ?array $newValue = null;

    private ?string $description = null;

    private ?string $userId = null;

    private ?string $ipAddress = null;

    private ?string $userAgent = null;

    private ?string $requestId = null;

    private ?string $category = null;

    private function __construct(AuditAction $action)
    {
        $this->action = $action;
    }

    // ---- Generic factory -------------------------------------------------

    /**
     * Start a fluent entry for any action verb. Typed helpers below delegate
     * to this for the verbs that do not warrant a dedicated method.
     */
    public static function log(AuditAction $action): self
    {
        return new self($action);
    }

    // ---- Auth & security -------------------------------------------------

    public static function acceptInvite(): self
    {
        return new self(AuditAction::ACCEPT_INVITE);
    }

    public static function requestPasswordReset(): self
    {
        return new self(AuditAction::REQUEST_PASSWORD_RESET);
    }

    public static function resetPassword(): self
    {
        return new self(AuditAction::RESET_PASSWORD);
    }

    public static function changePassword(): self
    {
        return new self(AuditAction::CHANGE_PASSWORD);
    }

    public static function verifyEmail(): self
    {
        return new self(AuditAction::VERIFY_EMAIL);
    }

    public static function changeEmail(): self
    {
        return new self(AuditAction::CHANGE_EMAIL);
    }

    public static function enableMfa(): self
    {
        return new self(AuditAction::ENABLE_MFA);
    }

    public static function disableMfa(): self
    {
        return new self(AuditAction::DISABLE_MFA);
    }

    public static function regenerateRecoveryCodes(): self
    {
        return new self(AuditAction::REGENERATE_RECOVERY_CODES);
    }

    public static function adminResetPassword(): self
    {
        return new self(AuditAction::ADMIN_RESET_PASSWORD);
    }

    public static function adminChangeEmail(): self
    {
        return new self(AuditAction::ADMIN_CHANGE_EMAIL);
    }

    public static function adminVerifyEmail(): self
    {
        return new self(AuditAction::ADMIN_VERIFY_EMAIL);
    }

    public static function deleteAccount(): self
    {
        return new self(AuditAction::DELETE_ACCOUNT);
    }

    public static function purgeUser(): self
    {
        return new self(AuditAction::PURGE_USER);
    }

    public static function revokeSessions(): self
    {
        return new self(AuditAction::REVOKE_SESSIONS);
    }

    public static function login(): self
    {
        return new self(AuditAction::LOGIN);
    }

    public static function logout(): self
    {
        return new self(AuditAction::LOGOUT);
    }

    public static function loginFailed(): self
    {
        return new self(AuditAction::LOGIN_FAILED);
    }

    // ---- Case Manager ----------------------------------------------------

    public static function approve(): self
    {
        return new self(AuditAction::APPROVE);
    }

    public static function reject(): self
    {
        return new self(AuditAction::REJECT);
    }

    public static function sendReferral(): self
    {
        return new self(AuditAction::SEND);
    }

    public static function close(): self
    {
        return new self(AuditAction::CLOSE);
    }

    public static function createCaseIssue(): self
    {
        return new self(AuditAction::CREATE_CASE_ISSUE);
    }

    public static function download(): self
    {
        return new self(AuditAction::DOWNLOAD);
    }

    public static function export(): self
    {
        return new self(AuditAction::EXPORT);
    }

    // ---- Agency Focal ----------------------------------------------------

    public static function acceptReferral(): self
    {
        return new self(AuditAction::ACCEPT);
    }

    public static function requestCompliance(): self
    {
        return new self(AuditAction::REQUEST_COMPLIANCE);
    }

    public static function resume(): self
    {
        return new self(AuditAction::RESUME);
    }

    public static function completeReferral(): self
    {
        return new self(AuditAction::COMPLETE);
    }

    public static function assignService(): self
    {
        return new self(AuditAction::ASSIGN_SERVICE);
    }

    public static function unassignService(): self
    {
        return new self(AuditAction::UNASSIGN_SERVICE);
    }

    public static function addRequirement(): self
    {
        return new self(AuditAction::ADD_REQUIREMENT);
    }

    public static function updateRequirement(): self
    {
        return new self(AuditAction::UPDATE_REQUIREMENT);
    }

    public static function removeRequirement(): self
    {
        return new self(AuditAction::REMOVE_REQUIREMENT);
    }

    public static function issueClientRequest(): self
    {
        return new self(AuditAction::ISSUE_CLIENT_REQUEST);
    }

    public static function issueLink(): self
    {
        return new self(AuditAction::ISSUE_LINK);
    }

    public static function reissueLink(): self
    {
        return new self(AuditAction::REISSUE_LINK);
    }

    public static function revokeLink(): self
    {
        return new self(AuditAction::REVOKE_LINK);
    }

    public static function resolveClientRequest(): self
    {
        return new self(AuditAction::RESOLVE_CLIENT_REQUEST);
    }

    public static function cancelClientRequest(): self
    {
        return new self(AuditAction::CANCEL_CLIENT_REQUEST);
    }

    public static function reopenClientRequest(): self
    {
        return new self(AuditAction::REOPEN_CLIENT_REQUEST);
    }

    // ---- System Admin ----------------------------------------------------

    public static function invite(): self
    {
        return new self(AuditAction::INVITE);
    }

    public static function resendInvite(): self
    {
        return new self(AuditAction::RESEND_INVITE);
    }

    public static function cancelInvite(): self
    {
        return new self(AuditAction::CANCEL_INVITE);
    }

    public static function deactivateUser(): self
    {
        return new self(AuditAction::DEACTIVATE);
    }

    public static function reactivateUser(): self
    {
        return new self(AuditAction::REACTIVATE);
    }

    public static function resetMfa(): self
    {
        return new self(AuditAction::RESET_MFA);
    }

    public static function toggleMaintenance(): self
    {
        return new self(AuditAction::TOGGLE_MAINTENANCE);
    }

    public static function updateSecurity(): self
    {
        return new self(AuditAction::UPDATE_SECURITY);
    }

    public static function sendReminder(): self
    {
        return new self(AuditAction::SEND_REMINDER);
    }

    // ---- OFW / public ----------------------------------------------------

    public static function register(): self
    {
        return new self(AuditAction::REGISTER);
    }

    public static function fileIntake(): self
    {
        return new self(AuditAction::FILE_INTAKE);
    }

    public static function replyClientRequest(): self
    {
        return new self(AuditAction::REPLY_CLIENT_REQUEST);
    }

    public static function uploadReplyDoc(): self
    {
        return new self(AuditAction::UPLOAD_REPLY_DOC);
    }

    public static function trackCase(): self
    {
        return new self(AuditAction::TRACK_CASE);
    }

    public static function submitSurvey(): self
    {
        return new self(AuditAction::SUBMIT_SURVEY);
    }

    public static function submitContact(): self
    {
        return new self(AuditAction::SUBMIT_CONTACT);
    }

    // ---- Fluent setters --------------------------------------------------

    /**
     * Set the causer. Defaults to the authenticated user when omitted.
     * Pass null explicitly for anonymous/system/CLI entries.
     */
    public function by(?User $user): self
    {
        $this->userId = $user?->getKey();

        return $this;
    }

    /**
     * Set the causer by id, for listeners and services that only have the id.
     */
    public function byId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Resolve the entity from a model, stamping both entity_id and the
     * durable entity_label snapshot so historic rows survive renames.
     */
    public function forEntity(Model|string|null $entity, ?string $label = null): self
    {
        if ($entity instanceof Model) {
            $this->entityId = (string) $entity->getKey();

            // Prefer an explicit label, then the model's own snapshot method,
            // then a caller-provided label.
            $this->entityLabel = $label
                ?? (method_exists($entity, 'getAuditEntityLabel') ? $entity->getAuditEntityLabel() : null);
        } elseif (is_string($entity) && $entity !== '') {
            $this->entityId = $entity;
            $this->entityLabel = $label;
        }

        return $this;
    }

    /**
     * Set entity_id and entity_label directly (for non-model entities like
     * access links identified by token, or batch operations).
     */
    public function entity(?string $id, ?string $label = null): self
    {
        $this->entityId = $id;
        $this->entityLabel = $label;

        return $this;
    }

    public function module(AuditModule $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function context(?array $old = null, ?array $new = null): self
    {
        $this->oldValue = $old;
        $this->newValue = $new;

        return $this;
    }

    /**
     * Override the auto-generated description. When omitted, the formatter
     * builds the target sentence from the verb + entity + context.
     */
    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Force a category (e.g. SECURITY) instead of the module/action default.
     */
    public function category(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function requestContext(?string $ip, ?string $userAgent, ?string $requestId): self
    {
        $this->ipAddress = $ip;
        $this->userAgent = $userAgent;
        $this->requestId = $requestId;

        return $this;
    }

    // ---- Persistence -----------------------------------------------------

    /**
     * Persist the entry. Runs inside `AuditLog::save()`'s own transaction so
     * the hash-chain advisory lock and the predecessor read share it.
     */
    public function write(): AuditLog
    {
        $request = request();

        $data = [
            'action' => $this->action->value,
            'module' => $this->module->value,
            'entity_id' => $this->entityId,
            'entity_label' => $this->entityLabel,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'user_id' => $this->userId ?? Auth::id(),
            'timestamp' => now(),
            'ip_address' => $this->ipAddress ?? ($request?->ip() ?? 'cli'),
            'user_agent' => $this->userAgent ?? ($request?->userAgent() ?? 'cli'),
            'request_id' => $this->requestId ?? (
                $request?->attributes->get('correlation_id')
                ?? $request?->header('X-Request-ID')
                ?? (string) Str::uuid()
            ),
        ];

        if ($this->category !== null) {
            $data['category'] = $this->category;
        }

        // Generate the description via the formatter unless the caller set
        // one explicitly. This mirrors AuditObserver's single-save pattern.
        if ($this->description === null) {
            try {
                $tempLog = new AuditLog($data);
                $data['description'] = app(AuditLogFormatter::class)->format($tempLog);
            } catch (\Throwable $e) {
                logger()->warning('AuditLogger description generation failed', ['exception' => $e->getMessage()]);
            }
        } else {
            $data['description'] = $this->description;
        }

        return AuditLog::create($data);
    }
}
