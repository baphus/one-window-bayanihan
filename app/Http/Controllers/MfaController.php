<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public function generateSecret(Request $request): JsonResponse
    {
        /** @var Google2FA $google2fa */
        $google2fa = app('pragmarx.google2fa');

        $secret = $google2fa->generateSecretKey();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret,
        );

        $request->session()->put('mfa_pending_secret', $secret);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function verifyAndEnable(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $secret = $request->session()->get('mfa_pending_secret');

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

        $user = $request->user();
        $user->mfa_secret = $secret;
        $user->mfa_recovery_codes = $this->generateRecoveryCodes();
        $user->mfa_enabled_at = now();
        $user->save();

        $request->session()->forget('mfa_pending_secret');

        return response()->json([
            'message' => 'Two-factor authentication has been enabled.',
            'recovery_codes' => $user->mfa_recovery_codes,
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

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
        ]);
    }

    public function getRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled_at === null) {
            return response()->json(['message' => 'MFA is not enabled.'], 403);
        }

        return response()->json([
            'recovery_codes' => $user->mfa_recovery_codes ?? [],
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->mfa_enabled_at === null) {
            return response()->json(['message' => 'MFA is not enabled.'], 403);
        }

        $codes = $this->generateRecoveryCodes();
        $user->mfa_recovery_codes = $codes;
        $user->save();

        return response()->json([
            'recovery_codes' => $codes,
        ]);
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(
                Str::random(4).'-'.Str::random(4).'-'.Str::random(4),
            );
        }

        return $codes;
    }
}
