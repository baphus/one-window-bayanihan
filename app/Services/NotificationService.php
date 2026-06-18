<?php

namespace App\Services;

use App\Mail\ClientUpdateMail;
use App\Models\CaseFile;
use App\Models\CaseNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
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
     */
    public function notifyOfw(
        CaseFile $case,
        string $clientEmail,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?string $relatedUrl = null,
    ): ?CaseNotification {
        if (empty($clientEmail)) {
            return null;
        }

        // Queue a friendly email to the client with case update details.
        Mail::to($clientEmail)->queue(new ClientUpdateMail(
            trackingNumber: $case->tracker_number,
            caseNumber: $case->case_number,
            title: $title,
            message: $message,
        ));

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
     * @return bool True if found and updated, false if not found
     */
    public function markAsRead(string $notificationId, string $notifiableType = 'user'): bool
    {
        if ($notifiableType === 'ofw') {
            $updated = CaseNotification::where('id', $notificationId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return $updated > 0;
        }

        // For authenticated users, find via the notifications table
        $notification = app(DatabaseNotification::class)
            ->where('id', $notificationId)
            ->whereNull('read_at')
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->update(['read_at' => now()]);

        return true;
    }

    /**
     * Mark all unread notifications as read for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model or client email string
     * @param  string  $notifiableType  'user' or 'ofw'
     * @return int Number of updated records
     */
    public function markAllAsRead(mixed $notifiable, string $notifiableType = 'user'): int
    {
        if ($notifiableType === 'ofw') {
            return CaseNotification::where('client_email', $notifiable)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return $notifiable->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Get the unread notification count for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model or client email string
     * @param  string  $notifiableType  'user' or 'ofw'
     */
    public function getUnreadCount(mixed $notifiable, string $notifiableType = 'user'): int
    {
        if ($notifiableType === 'ofw') {
            return CaseNotification::where('client_email', $notifiable)
                ->whereNull('read_at')
                ->count();
        }

        return $notifiable->unreadNotifications()->count();
    }

    /**
     * Get paginated notifications for a given notifiable entity.
     *
     * @param  mixed  $notifiable  User model or client email string
     * @param  string  $notifiableType  'user' or 'ofw'
     * @param  int  $perPage  Items per page (default: 20)
     */
    public function getNotifications(mixed $notifiable, string $notifiableType = 'user', int $perPage = 20): LengthAwarePaginator
    {
        if ($notifiableType === 'ofw') {
            return CaseNotification::where('client_email', $notifiable)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        return $notifiable->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
