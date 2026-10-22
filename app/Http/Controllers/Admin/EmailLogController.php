<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\Mail\DeliveryStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmailLog::orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->input('status');

            if (in_array($status, DeliveryStatus::all(), true)) {
                $query->where('status', $status);
            }
        }

        $logs = $query->paginate(15);

        return Inertia::render('Admin/System/EmailLogs/Index', [
            'logs' => $logs,
            'statuses' => DeliveryStatus::all(),
            'filters' => [
                'status' => $request->input('status', ''),
            ],
        ]);
    }

    public function resend(EmailLog $emailLog): RedirectResponse
    {
        if ($emailLog->status !== 'failed') {
            return back()->with('error', 'Only failed emails can be resent.');
        }

        // resent_at is stamped when a retry is put back on the queue and cleared
        // again if that retry fails, so a set value means a copy of this mail is
        // already in flight. Resending again would put a duplicate in the
        // recipient's inbox and leave two indistinguishable rows behind.
        if ($emailLog->resent_at !== null) {
            return back()->with('error', 'This email is already queued for another delivery attempt.');
        }

        // Prefer retrying the original failed job, identified by its uuid.
        $retried = $emailLog->job_uuid !== null
            && $this->retryFailedJob($emailLog->job_uuid);

        if ($retried) {
            $this->markResendPending($emailLog);
        } elseif ($emailLog->mailable_type && class_exists($emailLog->mailable_type)) {
            // Fallback: reconstruct a simple mailable and re-queue it
            $mailableClass = $emailLog->mailable_type;

            if (! is_subclass_of($mailableClass, Mailable::class)) {
                return back()->with('error', 'Cannot resend this email type automatically.');
            }

            $canRebuildSelf = method_exists($mailableClass, 'resend');

            // Application mailables take their payload (OTP codes, recipient
            // records, submitted form data) through constructor arguments. Once
            // the queued job carrying those arguments is gone there is nothing
            // left to reconstruct them from, so bail out gracefully instead of
            // letting `new` throw or queueing a data-less mail that fails on
            // every send. Only a genuinely argument-free mailable is rebuilt.
            if (! $canRebuildSelf && $this->constructorNeedsArguments($mailableClass)) {
                return back()->with('error', 'Cannot resend this email automatically: its mailable requires data that is not stored in this log, and the original failed job is no longer available.');
            }

            $mailable = new $mailableClass;

            if ($canRebuildSelf) {
                $mailable->resend($emailLog);
            } else {
                Mail::to($emailLog->to_email)->queue($mailable);
            }

            $this->markResendPending($emailLog);
        } else {
            return back()->with('error', 'Cannot resend: missing job reference and mailable class.');
        }

        return back()->with('success', 'Email has been re-queued for delivery.');
    }

    /**
     * Flag the row as having a delivery attempt in flight.
     *
     * The listener clears this again if the retry fails, which reopens the row
     * for another attempt without creating a second row for the same mail.
     */
    private function markResendPending(EmailLog $emailLog): void
    {
        $emailLog->forceFill(['resent_at' => now()])->save();
    }

    /**
     * Determine whether a mailable class can only be built by supplying arguments.
     *
     * Only a constructor declaring no parameters at all is safe to rebuild:
     * `new $mailableClass` would otherwise have to be given data the log row
     * does not hold, and a parameter that merely looks optional (nullable or
     * defaulted) still produces a mail with a null payload that fails on every
     * send — a permanent failure loop rather than a resend.
     *
     * @param  class-string  $mailableClass
     */
    private function constructorNeedsArguments(string $mailableClass): bool
    {
        $constructor = (new ReflectionClass($mailableClass))->getConstructor();

        return $constructor !== null && $constructor->getNumberOfParameters() > 0;
    }

    /**
     * Push the failed job behind $jobUuid back onto its queue via `queue:retry`.
     *
     * Returns false when no matching `failed_jobs` row exists so the caller can
     * fall back to re-queueing the mailable directly.
     */
    private function retryFailedJob(string $jobUuid): bool
    {
        $failedJob = DB::table('failed_jobs')->where('uuid', $jobUuid)->first();

        if ($failedJob === null) {
            return false;
        }

        // The uuid-based failer driver keys jobs by uuid; the legacy driver
        // keys them by primary key, and `queue:retry` matches on whatever the
        // configured driver uses.
        $identifier = config('queue.failed.driver') === 'database-uuids'
            ? $failedJob->uuid
            : $failedJob->id;

        Artisan::call('queue:retry', ['id' => [$identifier]]);

        return true;
    }
}
