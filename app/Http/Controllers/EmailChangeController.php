<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditModule;
use App\Http\Requests\EmailChangeInitRequest;
use App\Http\Requests\EmailChangeSendOtpRequest;
use App\Http\Requests\EmailChangeVerifyOtpRequest;
use App\Mail\EmailChangedNotification;
use App\Models\AuditLog;
use App\Services\AuditCategory;
use App\Services\OtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailChangeController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function init(EmailChangeInitRequest $request): Response
    {
        // The FormRequest already validated the current_password
        // Store the email_change_step in session
        $request->session()->put('email_change_step', 'new-email');
        $request->session()->forget(['email_change_hint', 'pending_new_email']);

        // Return to Profile/Edit with email_change_step prop.
        //
        // The shared props come from ProfileController because this page binds
        // them on mount: without them mfaEnabled was undefined (MFA shown as
        // disabled mid-flow) and notifications_config resolved to {} so the
        // next save overwrote the stored preferences with all-off defaults.
        return Inertia::render('Profile/Edit', [
            ...ProfileController::editProps($request),
            'email_change_step' => 'new-email',
            'email_change_hint' => null,
        ]);
    }

    public function sendOtp(EmailChangeSendOtpRequest $request): Response
    {
        $newEmail = $request->input('new_email');

        // Generate OTP to the new email
        $this->otpService->generate($newEmail, 'email_change', $request->session()->getId());

        // Create masked hint for the new email
        $emailParts = explode('@', $newEmail);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $newEmail;

        // Store pending new email and the current step in session
        $request->session()->put([
            'pending_new_email' => $newEmail,
            'email_change_step' => 'otp',
            'email_change_hint' => $hint,
        ]);

        Log::info('email_change_otp_sent', [
            'new_email' => $this->redactEmail($newEmail),
            'session_id' => substr($request->session()->getId(), 0, 8).'***',
        ]);

        return Inertia::render('Profile/Edit', [
            ...ProfileController::editProps($request),
            'email_change_step' => 'otp',
            'email_change_hint' => $hint,
        ]);
    }

    public function verifyOtp(EmailChangeVerifyOtpRequest $request)
    {
        $newEmail = $request->input('new_email');
        $otp = $request->input('otp');

        // Verify the OTP
        $verified = $this->otpService->verify(
            $newEmail,
            'email_change',
            $otp,
            $request->session()->getId(),
        );

        if (! $verified) {
            $request->session()->put('email_change_step', 'otp');

            return redirect()->route('profile.edit')
                ->withErrors(['otp' => 'That verification code was not accepted. Please check the latest code and try again.'])
                ->with('email_change_hint', $this->maskEmail($newEmail));
        }

        $user = $request->user();
        $oldEmail = $user->email;

        // Update email and mark as verified
        $user->email = $newEmail;
        $user->email_verified_at = now();
        $user->save();

        // Notify old email about the change
        Mail::to($oldEmail)->queue(
            new EmailChangedNotification($oldEmail, $newEmail, $user->name)
        );

        // Audit the email change (email is excluded from AuditObserver to prevent noise in general updates)
        AuditLog::create([
            'action' => AuditAction::UPDATE->value,
            'module' => AuditModule::USER->value,
            // Account-credential change: must appear in the Security view
            'category' => AuditCategory::SECURITY,
            'entity_id' => $user->id,
            'old_value' => ['email' => $oldEmail],
            'new_value' => ['email' => $newEmail],
            'description' => 'Email changed from '.$oldEmail.' to '.$newEmail,
            'user_id' => $user->id,
            'timestamp' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?? '',
            'request_id' => $request->attributes->get('correlation_id') ?? $request->header('X-Request-ID') ?? (string) Str::uuid(),
        ]);

        // Clear email change session state so the section resets
        $request->session()->forget(['email_change_step', 'email_change_hint', 'pending_new_email']);

        Log::info('email_change_successful', [
            'user_id' => $user->id,
            'old_email' => $this->redactEmail($oldEmail),
            'new_email' => $this->redactEmail($newEmail),
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Your email address has been changed successfully.');
    }

    private function redactEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (strlen($parts[0]) <= 2) {
            return $email;
        }

        return substr($parts[0], 0, 2).'***@'.$parts[1];
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2 || strlen($parts[0]) <= 2) {
            return $email;
        }

        return substr($parts[0], 0, 2).str_repeat('*', strlen($parts[0]) - 2).'@'.$parts[1];
    }
}
