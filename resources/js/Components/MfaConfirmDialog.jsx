import { useEffect, useRef, useState } from 'react';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { csrfToken, isFreshMfa } from '@/lib/mfa';

/**
 * Shared fresh-MFA confirmation used by administrator resets, policy saves
 * and self-service MFA actions. Successful confirmation opens the five-minute
 * window server-side; the optional reason/password are handed back to the
 * caller for the mutation that follows.
 */
export default function MfaConfirmDialog({
    open,
    title = 'Confirm it’s you',
    description = 'Enter a code from your authenticator app, or use one of your recovery codes.',
    confirmLabel = 'Confirm',
    reasonRequired = false,
    reasonLabel = 'Reason',
    passwordRequired = false,
    passwordLabel = 'Your password',
    confirmedUntil = null,
    forceProof = false,
    proofOnly = false,
    collectOnly = false,
    reason: initialReason = '',
    error: serverError = '',
    onError,
    onCancel,
    onConfirmed,
}) {
    const [mode, setMode] = useState('totp');
    const [otp, setOtp] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [reason, setReason] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const dialogRef = useRef(null);
    const triggerRef = useRef(null);

    const proofRequired = forceProof || !isFreshMfa(confirmedUntil);

    useEffect(() => {
        if (!open) return;
        setMode('totp');
        setOtp('');
        setRecoveryCode('');
        setReason(initialReason);
        setPassword('');
        setError('');
        setSubmitting(false);
    }, [open, initialReason]);

    useEffect(() => {
        if (!open) return undefined;
        triggerRef.current = document.activeElement;
        const dialog = dialogRef.current;
        const target = dialog?.querySelector('[data-dialog-initial-focus]')
            || dialog?.querySelector('input:not([disabled]), button:not([disabled])');
        (target || dialog)?.focus();

        return () => {
            const trigger = triggerRef.current;
            if (trigger?.isConnected && typeof trigger.focus === 'function') trigger.focus();
        };
    }, [open]);

    useEffect(() => {
        if (!open) return undefined;

        const containFocus = (event) => {
            if (event.key === 'Escape' && !submitting) {
                onCancel?.();
                return;
            }
            if (event.key !== 'Tab') return;
            const dialog = dialogRef.current;
            if (!dialog) return;
            const focusable = [...dialog.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )].filter((element) => element.getAttribute('aria-hidden') !== 'true');
            if (focusable.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', containFocus);
        return () => document.removeEventListener('keydown', containFocus);
    }, [open]);

    if (!open) return null;

    const proofMissing = proofRequired && (mode === 'totp' ? otp.length !== 6 : !recoveryCode.trim());
    const disabled = submitting || proofMissing || (reasonRequired && !reason.trim()) || (passwordRequired && !password.trim());
    const shownError = error || serverError;

    async function handleConfirm() {
        if (disabled) return;
        setSubmitting(true);
        setError('');

        if (proofRequired && !collectOnly) {
            try {
                const response = await fetch(route('profile.mfa.confirm'), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(mode === 'totp' ? { otp } : { recovery_code: recoveryCode.trim() }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const message = data.message
                        || data.errors?.mfa_confirmation?.[0]
                        || data.errors?.otp?.[0]
                        || data.errors?.recovery_code?.[0]
                        || 'MFA confirmation failed. Please try again.';
                    setError(message);
                    onError?.(message);
                    setSubmitting(false);
                    return;
                }
            } catch {
                setError('Failed to reach the server. Please try again.');
                onError?.('Failed to reach the server. Please try again.');
                setSubmitting(false);
                return;
            }
        }

        if (collectOnly) {
            onConfirmed({ proof: proofRequired ? (mode === 'totp' ? { otp } : { recovery_code: recoveryCode.trim() }) : {} });
        } else if (proofOnly) {
            onConfirmed(proofRequired ? (mode === 'totp' ? { otp } : { recovery_code: recoveryCode.trim() }) : {});
        } else {
            onConfirmed({
                reason: reason.trim(),
                password,
                proof: proofRequired ? (mode === 'totp' ? { otp } : { recovery_code: recoveryCode.trim() }) : {},
                confirmedUntil: proofRequired ? undefined : confirmedUntil,
            });
        }
        setSubmitting(false);
    }

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4" onClick={onCancel}>
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby="mfa-confirm-title"
                tabIndex={-1}
                className="w-full max-w-md rounded-lg owb-modal-animate bg-white shadow-xl"
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex flex-col items-center px-6 py-5 text-center">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50">
                        <span className="material-symbols-outlined text-[22px] text-blue-600">verified_user</span>
                    </div>
                    <h2 id="mfa-confirm-title" className="mt-3 text-[16px] font-extrabold text-slate-900">{title}</h2>
                    <p className="mt-1.5 text-[13px] leading-relaxed text-slate-600">{description}</p>
                </div>

                <div className="space-y-4 px-6 pb-4">
                    {proofRequired ? (
                        <>
                            <div className="flex rounded-md border border-slate-200 p-0.5 text-[12px] font-bold">
                                <button
                                    type="button"
                                    onClick={() => { setMode('totp'); setError(''); }}
                                    className={`flex-1 rounded px-3 py-1.5 transition-colors ${mode === 'totp' ? 'bg-blue-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                                >
                                    Authenticator
                                </button>
                                <button
                                    type="button"
                                    onClick={() => { setMode('recovery'); setError(''); }}
                                    className={`flex-1 rounded px-3 py-1.5 transition-colors ${mode === 'recovery' ? 'bg-blue-900 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                                >
                                    Recovery code
                                </button>
                            </div>

                            {mode === 'totp' ? (
                                <div>
                                    <InputLabel htmlFor="mfa-confirm-otp" value="Authentication code" />
                                    <TextInput
                                        id="mfa-confirm-otp"
                                        className="mt-1 block w-full text-center font-mono tracking-[0.4em]"
                                        value={otp}
                                        onChange={(e) => { setOtp(e.target.value.replace(/\D/g, '').slice(0, 6)); setError(''); }}
                                        placeholder="000000"
                                        maxLength={6}
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        data-dialog-initial-focus
                                    />
                                </div>
                            ) : (
                                <div>
                                    <InputLabel htmlFor="mfa-confirm-recovery" value="Recovery code" />
                                    <TextInput
                                        id="mfa-confirm-recovery"
                                        className="mt-1 block w-full font-mono"
                                        value={recoveryCode}
                                        onChange={(e) => { setRecoveryCode(e.target.value.slice(0, 64)); setError(''); }}
                                        placeholder="Enter a recovery code"
                                        autoComplete="off"
                                        data-dialog-initial-focus
                                    />
                                </div>
                            )}
                        </>
                    ) : (
                        <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-[12px] font-medium text-emerald-800">
                            Your recent MFA sign-in is still valid, so only the details below are needed.
                        </p>
                    )}

                    {passwordRequired && !proofOnly && (
                        <div>
                            <InputLabel htmlFor="mfa-confirm-password" value={passwordLabel} />
                            <TextInput
                                id="mfa-confirm-password"
                                type="password"
                                className="mt-1 block w-full"
                                value={password}
                                onChange={(e) => { setPassword(e.target.value); setError(''); }}
                                placeholder="Enter your password"
                                autoComplete="current-password"
                            />
                        </div>
                    )}

                    {reasonRequired && (
                        <div>
                            <InputLabel htmlFor="mfa-confirm-reason" value={reasonLabel} />
                            <TextInput
                                id="mfa-confirm-reason"
                                className="mt-1 block w-full"
                                value={reason}
                                onChange={(e) => { setReason(e.target.value); setError(''); }}
                                placeholder="Why is this change needed?"
                                maxLength={500}
                            />
                        </div>
                    )}

                    {shownError && <InputError message={shownError} />}
                </div>

                <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={submitting}
                        className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={disabled}
                        className="h-9 rounded-[3px] bg-blue-900 px-4 text-[12px] font-bold text-white transition-colors hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {submitting ? 'Confirming…' : confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
