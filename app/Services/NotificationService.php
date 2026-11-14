<?php

namespace App\Services;

use App\Http\Middleware\HandleInertiaRequests;
use App\Mail\ClientUpdateMail;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    /**
     * Dispatch a notification to authenticated users via Laravel's notification system.
     */
    public function notifyUsers(Collection|array $users, Notification $notification): void
    {
        NotificationFacade::send($users, $notification);
    }

    /**
     * Create a CaseNotification record for an OFW client (unauthenticated user).
     *
     * The CaseNotification row is written inline so it shares the caller's
     * transaction, but the client email is pushed to the queue after commit.
     * The queue connections run with `after_commit => false`, so a push made
     * before COMMIT hands the worker a job it can run while the surrounding
     * transaction later rolls back — the client would be emailed about a case
     * update that never persisted. `DB::afterCommit` runs the callback straight
     * away when no transaction is open, so callers outside a transaction keep
     * their current behaviour.
     *
     * @param  bool  $sendEmail  Queue a ClientUpdateMail alongside the in-app
     *                           notification. Callers that ship their own
     *                           dedicated email (e.g. IntakePublishedMail when
     *                           an intake is accepted) pass false to avoid the
     *                           client receiving two emails for one event.
     */
    public function notifyOfw(
        CaseFile $case,
        string $clientEmail,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $relatedUrl = null,
        bool $sendEmail = true,
    ): ?CaseNotification {
        // Contact address is the case's own intake snapshot. The caller-supplied
        // address is ignored so a changed account email or an email-based
        // lookup can never redirect a case notice to a different person.
        $clientEmail = $case->email ?? '';
        if (empty($clientEmail)) {
            return null;
        }

        if ($sendEmail) {
            // Queue a friendly email to the client with case update details.
            DB::afterCommit(fn () => Mail::to($clientEmail)->queue(new ClientUpdateMail(
                case: $case,
                message: $message,
                updatedBy: 'system',
            )));
        }

        return CaseNotification::create([
            'case_id' => $case->id,
            'client_email' => $clientEmail,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'related_url' => $relatedUrl,
        ]);
    }

    /**
     * Convenience method: notify both authenticated users AND OFW in one call.
     */
    public function notifyAll(
        CaseFile $case,
        Collection|array $users,
        string $clientEmail,
        Notification $notification,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $relatedUrl = null,
    ): void {
        $this->notifyUsers($users, $notification);
        $this->notifyOfw($case, $clientEmail, $type, $title, $message, $data, $relatedUrl);
    }

    /**
     * Mark a single notification as read.
     *
     * @param  string  $notificationId  UUID of the notification
     * @param  string  $notifiableType  'user' or 'ofw'
     * @param  mixed  $notifiable  The notifiable entity (User model; 'ofw' scopes by explicit case ownership)
     * @return bool True if found and updated, false if not found
     */
    public function markAsRead(string $notificationId, string $notifiableType = 'user', mixed $notifiable = null): bool
    {
        if ($notifiableType === 'ofw') {
            $query = $this->ownedCaseNotifications($notifiable)->where('id', $notificationId)
                ->whereNull('read_at');

            $updated = $query->update(['read_at' => now()]);

            return $updated > 0;
        }

        // For authenticated users, scope to the user's notifications. A
        // notifiable is required: matching by id alone would let any caller
        // mark another user's notification read.
        if (! $notifiable) {
            return false;
        }

        $notification = $notifiable->notifications()
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->update(['read_at' => now()]);

        // Invalidate cached unread count for this user
        if ($notifiable && method_exists($notifiable, 'getKey')) {
            HandleInertiaRequests::invalidateNotificationCount($notifiable->getKey());
        }

        return true;
    }

    /**
     * Mark all unread notifications as read for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model (OFW access requires explicit case ownership)
     * @param  string  $notifiableType  'user' or 'ofw'
     * @return int Number of updated records
     */
    public function markAllAsRead(mixed $notifiable, string $notifiableType = 'user'): int
    {
        if ($notifiableType === 'ofw') {
            return $this->ownedCaseNotifications($notifiable)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $count = $notifiable->unreadNotifications()->update(['read_at' => now()]);

        // Invalidate cached unread count for this user
        if ($count > 0 && method_exists($notifiable, 'getKey')) {
            HandleInertiaRequests::invalidateNotificationCount($notifiable->getKey());
        }

        return $count;
    }

    /**
     * Get the unread notification count for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model (OFW access requires explicit case ownership)
     * @param  string  $notifiableType  'user' or 'ofw'
     */
    public function getUnreadCount(mixed $notifiable, string $notifiableType = 'user'): int
    {
        if ($notifiableType === 'ofw') {
            return $this->ownedCaseNotifications($notifiable)
                ->whereNull('read_at')
                ->count();
        }

        return $notifiable->unreadNotifications()->count();
    }

    /**
     * Get paginated notifications for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model (OFW access requires explicit case ownership)
     * @param  string  $notifiableType  'user' or 'ofw'
     * @param  int  $perPage  Items per page (default: 20)
     */
    public function getNotifications(mixed $notifiable, string $notifiableType = 'user', int $perPage = 20): LengthAwarePaginator
    {
        if ($notifiableType === 'ofw') {
            return $this->ownedCaseNotifications($notifiable)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        return $notifiable->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    private function ownedCaseNotifications(mixed $owner): Builder
    {
        return CaseNotification::query()->whereHas('caseFile', fn (Builder $query) => $query
            ->clientVisible()->where('is_deleted', false)
            ->where('ofw_user_id', $owner instanceof User ? $owner->id : null)
            ->whereNotNull('ofw_user_id'));
    }

    /**
     * Rewrite a stored notification deep-link into an OFW-safe destination so
     * client payloads never carry case-manager URLs, and link notifications
     * about client requests straight to that request. Staff /cases/{id} links
     * become the OFW case page; /referrals/{id} links become the OFW
     * agency-milestones page for the same case and referral (ids come from
     * the notification payload, never parsed out of the staff URL). Public
     * /track links pass through untouched; anything unrecognized is dropped
     * rather than shipped. Returns the related_url-derived destination or
     * null, leaving the caller free to fall back to the case page.
     *
     * @param  array<string, mixed>  $data
     */
    public function ofwSafeRelatedUrl(?string $url, array $data, string $caseId): ?string
    {
        // Client-request notifications carry the request in the payload, so
        // they deep-link to the request itself even when related_url is null.
        $requestId = $data['request_id'] ?? null;
        $referralId = $data['referral_id'] ?? null;
        if (is_string($requestId) && $requestId !== '' && is_string($referralId) && $referralId !== '') {
            return route('ofw.case.requests.show', [
                'case' => $caseId,
                'referral' => $referralId,
                'request' => $requestId,
            ]);
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#^/cases/[^/]+$#', $path) === 1) {
            return route('ofw.case.show', $caseId);
        }

        if (preg_match('#^/referrals/[^/]+$#', $path) === 1) {
            if (! is_string($referralId) || $referralId === '') {
                return null;
            }

            return route('ofw.case.milestones', ['case' => $caseId, 'referral' => $referralId]);
        }

        if (str_starts_with($path, '/track')) {
            return $url;
        }

        return null;
    }
}
