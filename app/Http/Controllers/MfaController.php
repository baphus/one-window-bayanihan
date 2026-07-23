<?php

namespace App\Http\Controllers;

use App\Services\MfaPendingState;
use App\Services\MfaService;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FALaravel\Google2FA;

class MfaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->mfa_enabled_at !== null,
            'enabled_at' => $user->mfa_enabled_at?->toIso8601String(),
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
        $user->save();

        $pendingState->clear($request);
        $pendingState->markAuthenticated($request, $user);

        SecurityAuditLogger::log('mfa', sprintf('%s enabled two-factor authentication', $user->name));

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }

        $user = $request->user();

        $user->mfa_secret = null;
        $user->mfa_recovery_codes = null;
        $user->mfa_enabled_at = null;
        $user->save();

        SecurityAuditLogger::log('mfa', sprintf('%s disabled two-factor authentication', $user->name));

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled_at === null) {
            return response()->json(['message' => 'MFA is not enabled.'], 403);
        }

        if (! $request->has('password') || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password confirmation is required.'], 403);
        }

        $mfaService = app(MfaService::class);
        $codes = $mfaService->generateRecoveryCodes();
        $user->mfa_recovery_codes = $mfaService->hashRecoveryCodes($codes);
        $user->save();

        SecurityAuditLogger::log('mfa', sprintf('%s regenerated MFA recovery codes', $user->name));

        return response()->json([
            'recovery_codes' => $codes,
        ]);
    }
}
