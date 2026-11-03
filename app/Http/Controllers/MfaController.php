<?php

namespace App\Http\Controllers;

use App\Enums\AuditModule;
use App\Notifications\MfaStatusChangedNotification;
use App\Services\AuditLogger;
use App\Services\MfaPendingState;
use App\Services\MfaPolicyService;
use App\Services\MfaService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FALaravel\Google2FA;

class MfaController extends Controller
{
    public function status(Request $request, MfaPendingState $pendingState, MfaPolicyService $policy): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->mfa_enabled_at !== null,
            'enabled_at' => $user->mfa_enabled_at?->toIso8601String(),
            'required' => $policy->requiresEnrollment($user),
            'confirmed_until' => $pendingState->confirmedUntil($request, $user),
        ]);
    }

    public function confirm(Request $request, MfaPendingState $pendingState, MfaService $mfa): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],
            'recovery_code' => ['required_without:otp', 'nullable', 'string', 'max:64'],
        ]);
        $user = $request->user();

        if ($user->mfa_enabled_at === null) {
            throw ValidationException::withMessages(['mfa_confirmation' => 'MFA must be enabled before confirming.']);
        }

        $valid = isset($validated['recovery_code'])
            ? $mfa->consumeRecoveryCode($user, $validated['recovery_code'])
            : $mfa->consumeTotp($user, $validated['otp']);

        if (! $valid) {
            $field = isset($validated['recovery_code']) ? 'recovery_code' : 'otp';
            throw ValidationException::withMessages([
                'mfa_confirmation' => 'MFA confirmation failed. Please try again.',
                $field => 'The code is invalid or has already been used.',
            ]);
        }

        $pendingState->confirm($request, $user);

        return response()->json([
            'confirmed' => true,
            'confirmed_until' => $pendingState->confirmedUntil($request, $user),
        ]);
    }

    public function generateSecret(Request $request, MfaPendingState $pendingState): JsonResponse
    {
        if ($request->user()->mfa_enabled_at !== null) {
            return response()->json(['message' => 'MFA is already enabled.'], 409);
        }
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');

        $secret = $google2fa->generateSecretKey();

        $qrCodeSvg = $google2fa->getQRCodeInline(
            config('app.name'),
            $request->user()->email,
            $secret,
        );

        // Wrap SVG as a data URL for use in <img src>
        $qrCodeUrl = 'data:image/svg+xml;base64,'.base64_encode($qrCodeSvg);

        $request->session()->put(MfaPendingState::SECRET_KEY, $secret);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function verifyAndEnable(Request $request, MfaPendingState $pendingState): JsonResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        if ($user->mfa_enabled_at !== null) {
            return response()->json(['message' => 'MFA is already enabled.'], 409);
        }

        $secret = $request->session()->get(MfaPendingState::SECRET_KEY);

        if (! $secret) {
            throw ValidationException::withMessages([
                'otp' => 'Please generate a new secret first.',
            ]);
        }

        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');

        $valid = $google2fa->verifyKey($secret, $request->input('otp'));

        if (! $valid) {
            throw ValidationException::withMessages([
                'otp' => 'The code is invalid. Please try again.',
            ]);
        }

        $mfaService = app(MfaService::class);
        $codes = $mfaService->generateRecoveryCodes();

        $user->mfa_secret = $secret;
        $user->mfa_recovery_codes = $mfaService->hashRecoveryCodes($codes);
        $user->mfa_enabled_at = now();
        $user->mfa_last_totp_counter = null;
        $user->save();

        $pendingState->clear($request);
        $pendingState->markAuthenticated($request, $user);
        $pendingState->confirm($request, $user);

        AuditLogger::enableMfa()
            ->by($user)
            ->forEntity($user, $user->name)
            ->module(AuditModule::MFA)
            ->write();

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(Request $request, MfaPendingState $pendingState, MfaService $mfa, MfaPolicyService $policy): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'otp' => ['required_without:recovery_code', 'nullable', 'string', 'size:6'],
            'recovery_code' => ['required_without:otp', 'nullable', 'string', 'max:64'],
        ]);
        $user = $request->user();

        if ($policy->requiresEnrollment($user)) {
            throw ValidationException::withMessages(['mfa_policy' => 'MFA is required for your account and cannot be disabled.']);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $valid = isset($validated['recovery_code'])
            ? $mfa->consumeRecoveryCode($user, $validated['recovery_code'])
            : $mfa->consumeTotp($user, $validated['otp']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'mfa_confirmation' => 'MFA confirmation failed. Please try again.',
                isset($validated['recovery_code']) ? 'recovery_code' : 'otp' => 'The code is invalid or has already been used.',
            ]);
        }

        $user->mfa_secret = null;
        $user->mfa_recovery_codes = null;
        $user->mfa_enabled_at = null;
        $user->mfa_last_totp_counter = null;
        $user->save();

        app(UserService::class)->invalidateSessions($user, 'self_mfa_disabled', $request->session()->getId());
        $pendingState->clear($request);
        $pendingState->confirm($request, $user);

        AuditLogger::disableMfa()
            ->by($user)
            ->forEntity($user, $user->name)
            ->module(AuditModule::MFA)
            ->context(['enabled' => true], ['enabled' => false])
            ->write();

        $user->notify(new MfaStatusChangedNotification('disabled', false));

        return response()->json(['message' => 'Two-factor authentication has been disabled.']);
    }

    public function regenerateRecoveryCodes(Request $request, MfaPendingState $pendingState): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled_at === null) {
            return response()->json(['message' => 'MFA is not enabled.'], 403);
        }

        if (! $request->has('password') || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password confirmation is required.'], 403);
        }

        if (! $pendingState->ensureConfirmed($request, $user, $mfaService = app(MfaService::class))) {
            throw ValidationException::withMessages(['mfa_confirmation' => 'Fresh MFA confirmation is required.']);
        }

        $codes = $mfaService->generateRecoveryCodes();
        $user->mfa_recovery_codes = $mfaService->hashRecoveryCodes($codes);
        $user->save();

        AuditLogger::regenerateRecoveryCodes()
            ->by($user)
            ->forEntity($user, $user->name)
            ->module(AuditModule::MFA)
            ->write();

        return response()->json([
            'recovery_codes' => $codes,
        ]);
    }
}
