<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Settings\SettingRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Email delivery log for admins.
 *
 * email_logs has recorded every outbound message since it was introduced —
 * recipient, subject, status, the provider's error, and the delivery events
 * the Resend webhook feeds back — but nothing read it. This is the page that
 * makes "the client says they never got the link" answerable without a
 * database client.
 */
class EmailLogController extends Controller
{
    use ResolvesPerPage;

    /** Statuses EmailEventSubscriber writes. */
    public const STATUSES = ['sent', 'failed'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $query = EmailLog::query()->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            // Recipient or subject: the two things an admin has when a user
            // reports a missing email.
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('to_email', 'ilike', $term)
                    ->orWhere('subject', 'ilike', $term);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'].' 23:59:59');
        }

        $logs = $query->paginate($this->perPage($request, 25))
            ->appends($request->except(['page', 'cursor']))
            ->through(fn (EmailLog $log) => $this->present($log));

        return Inertia::render('Admin/EmailLogs/Index', [
            'logs' => $logs,
            'statuses' => self::STATUSES,
            'filterValues' => (object) $request->only(['status', 'search', 'date_from', 'date_to', 'per_page']),
            // Read from the setting, not from config: config/email_logs.php does
            // not exist, so config('email_logs.retention_days', 90) always
            // answered 90 and the page could claim a retention window the
            // pruner was not using. Same class of bug as the phantom
            // reports.retention_days key this project set out to remove.
            'retentionDays' => (int) SettingRegistry::value('email_log_retention_days'),
        ]);
    }

    /**
     * One message, with its provider events.
     *
     * The detail view exists because the list can only say "failed"; what an
     * admin needs next is the reason, which lives on the row and in the events
     * the provider reported afterwards.
     */
    public function show(EmailLog $emailLog)
    {
        $emailLog->load(['events' => fn ($q) => $q->orderBy('occurred_at')]);

        return Inertia::render('Admin/EmailLogs/Show', [
            'log' => $this->present($emailLog) + [
                'events' => $emailLog->events->map(fn ($event) => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Shape a row for the page.
     *
     * Only the columns the page renders: the provider message id and job uuid
     * are debugging aids with no place on an admin screen.
     *
     * @return array<string, mixed>
     */
    private function present(EmailLog $log): array
    {
        return [
            'id' => $log->id,
            'to_email' => $log->to_email,
            'subject' => $log->subject,
            'mailable_type' => $log->mailable_type,
            'status' => $log->status,
            'error_message' => $log->error_message,
            'sent_at' => $log->sent_at?->toIso8601String(),
            'delivered_at' => $log->delivered_at?->toIso8601String(),
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
