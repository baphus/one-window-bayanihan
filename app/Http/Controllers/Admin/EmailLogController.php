<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EmailLog::orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $status = $request->input('status');

            if (in_array($status, ['sent', 'failed'])) {
                $query->where('status', $status);
            }
        }

        $logs = $query->paginate(15);

        return Inertia::render('Admin/System/EmailLogs/Index', [
            'logs' => $logs,
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

        // Prefer job_uuid-based retry from failed_jobs table
        if ($emailLog->job_uuid) {
            Queue::retry($emailLog->job_uuid);
        } elseif ($emailLog->mailable_type && class_exists($emailLog->mailable_type)) {
            // Fallback: reconstruct a simple mailable and re-queue it
            $mailableClass = $emailLog->mailable_type;

            if (is_subclass_of($mailableClass, Mailable::class)) {
                $mailable = new $mailableClass;

                if (method_exists($mailable, 'resend')) {
                    $mailable->resend($emailLog);
                } else {
                    Mail::to($emailLog->to_email)->queue($mailable);
                }
            } else {
                return back()->with('error', 'Cannot resend this email type automatically.');
            }
        } else {
            return back()->with('error', 'Cannot resend: missing job reference and mailable class.');
        }

        return back()->with('success', 'Email has been re-queued for delivery.');
    }
}
