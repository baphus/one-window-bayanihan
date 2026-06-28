<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailChangeInitRequest;
use App\Http\Requests\EmailChangeSendOtpRequest;
use App\Http\Requests\EmailChangeVerifyOtpRequest;
use App\Mail\EmailChangedNotification;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Services\OtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmailChangeController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    public function init(EmailChangeInitRequest $request)
    {
        // The FormRequest already validated the current_password
        // Store the email_change_step in session
        $request->session()->put('email_change_step', 1);

        // Return to Profile/Edit with email_change_step prop
        return Inertia::render('Profile/Edit', [
            'email_change_step' => 'new-email',
            'email_change_hint' => null,
            'email_change_debug_otp' => null,
        ]);
    }

    public function sendOtp(EmailChangeSendOtpRequest $request)
    {
        $newEmail = $request->input('new_email');

        // Generate OTP to the new email
        $otp = $this->otpService->generate($newEmail, 'email_change', $request->session()->getId());

        // Store pending new email in session
        $request->session()->put('pending_new_email', $newEmail);

        // Create masked hint for the new email
        $emailParts = explode('@', $newEmail);
        $hint = strlen($emailParts[0]) > 2
            ? substr($emailParts[0], 0, 2).str_repeat('*', strlen($emailParts[0]) - 2).'@'.$emailParts[1]
            : $newEmail;

        Log::info('email_change_otp_sent', [
            'new_email' => $this->redactEmail($newEmail),
            'session_id' => substr($request->session()->getId(), 0, 8).'***',
        ]);

        return Inertia::render('Profile/Edit', [
            'email_change_step' => 'otp',
            'email_change_hint' => $hint,
            'email_change_debug_otp' => (SystemSetting::getValue('debug_otp_enabled', false) && app()->environment('local', 'staging', 'testing')) ? $otp : null,
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
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired OTP. Please request a new code.',
            ]);
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

        // Create audit log entry
        AuditLog::create([
            'action' => 'UPDATE',
            'module' => 'email',
            'entity_id' => $user->id,
            'old_value' => ['email' => $oldEmail],
            'new_value' => ['email' => $newEmail],
            'user_id' => $user->id,
            'timestamp' => now(),
        ]);

        // Invalidate sessions except current — regenerate session ID
        $request->session()->regenerate(true);

        // Clear email change session vars
        $request->session()->forget(['email_change_step', 'pending_new_email']);

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
}
