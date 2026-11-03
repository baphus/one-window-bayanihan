import { useEffect, useState } from 'react';
import { CardSection } from '@/Components/ui/CardSection';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import MfaConfirmDialog from '@/Components/MfaConfirmDialog';
import useMfaStatus from '@/Hooks/useMfaStatus';
import { useToast } from '@/Hooks/useToast';
import TextInput from '@/Components/TextInput';

export default function MfaSetup({ mfaEnabled, mfaRequired = true }) {
    const [enabled, setEnabled] = useState(mfaEnabled);
    const { status, isFresh, refresh } = useMfaStatus();
    const required = status?.required ?? mfaRequired;
    const [step, setStep] = useState('idle'); // idle | show_qr | verifying | enabled | recovery
    const [secret, setSecret] = useState('');
    const [qrUrl, setQrUrl] = useState('');
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState([]);
    const [showRecovery, setShowRecovery] = useState(false);
    const toast = useToast();
    const [loading, setLoading] = useState(false);

    // Password-confirm dialog state (replaces simple ConfirmDialog for disable/regenerate)
    const [confirmAction, setConfirmAction] = useState(null); // null | 'disable' | 'regenerate'
    const [confirmPassword, setConfirmPassword] = useState('');
    const [confirmError, setConfirmError] = useState('');

    // Fresh-MFA gate. The shared confirmation opens the five-minute window;
    // the password dialog below still runs against the configured endpoint.
    const [mfaAction, setMfaAction] = useState(null); // null | 'disable' | 'regenerate'
    const [forceMfaProof, setForceMfaProof] = useState(false);
    const [proofData, setProofData] = useState({});

    useEffect(() => {
        if (status) setEnabled(Boolean(status.enabled));
    }, [status]);

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function askForPassword(action) {
        setConfirmAction(action);
        setConfirmPassword('');
        setConfirmError('');
    }

    function beginMfaAction(action) {
        if (required) return;
        // Disable requires a one-time factor alongside the password even when
        // a fresh window exists; regeneration only needs the fresh window.
        if (action === 'regenerate' && isFresh && !forceMfaProof) {
            askForPassword(action);
            return;
        }
        setForceMfaProof(action === 'disable');
        setMfaAction(action);
    }

    function handleEnable() {
        setLoading(true);
        setError('');

        fetch(route('profile.mfa.generate'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })
        .then(r => r.json())
        .then(data => {
            setSecret(data.secret);
            setQrUrl(data.qr_code_url);
            setStep('show_qr');
        })
        .catch(() => { setError('Failed to generate MFA secret.'); toast.error('Failed to generate MFA secret.'); })
        .finally(() => setLoading(false));
    }

    function handleVerify() {
        if (!otp || otp.length !== 6) {
            setError('Please enter a valid 6-digit code.');
            return;
        }
        setLoading(true);
        setError('');

        fetch(route('profile.mfa.verify'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ otp }),
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) {
                setError(data.message || data.errors?.otp?.[0] || 'Invalid code. Please try again.');
                toast.error(data.message || data.errors?.otp?.[0] || 'Invalid code. Please try again.');
                return;
            }
            setEnabled(true);
            setStep('recovery');
            setRecoveryCodes(data.recovery_codes || []);
            toast.success('Two-factor authentication enabled successfully!');
        })
        .catch(() => { setError('Verification failed.'); toast.error('Verification failed.'); })
        .finally(() => setLoading(false));
    }

    function handleDisable() {
        beginMfaAction('disable');
    }

    function confirmDisable() {
        if (!confirmPassword) {
            setConfirmError('Please enter your password.');
            return;
        }
        if (!proofData.otp && !proofData.recovery_code) {
            setConfirmAction(null);
            setMfaAction('disable');
            setForceMfaProof(true);
            return;
        }
        setLoading(true);
        setConfirmError('');

        fetch(route('profile.mfa.disable'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ password: confirmPassword, otp: proofData.otp, recovery_code: proofData.recovery_code }),
        })
        .then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const error = new Error(data.errors?.password?.[0] || data.errors?.mfa_policy?.[0] || data.message || 'Incorrect password.');
                error.mfaStale = Boolean(data.errors?.mfa_confirmation);
                error.mfaPolicy = Boolean(data.errors?.mfa_policy);
                throw error;
            }
            setEnabled(false);
            setStep('idle');
            setSecret('');
            setQrUrl('');
            setOtp('');
            setRecoveryCodes([]);
            setShowRecovery(false);
            setConfirmAction(null);
            setConfirmPassword('');
            refresh();
            toast.success('Two-factor authentication disabled.');
        })
        .catch((e) => {
            if (e.mfaStale) {
                setConfirmAction(null);
                setForceMfaProof(true);
                setMfaAction('disable');
            } else if (e.mfaPolicy) {
                setConfirmAction(null);
                setConfirmError(e.message);
                setEnabled(true);
                refresh();
            }
            setConfirmError(e.message || 'An error occurred. Please try again.');
            setConfirmPassword('');
            toast.error(e.message || 'An error occurred. Please try again.');
        })
        .finally(() => setLoading(false));
    }

    function handleRegenerateCodes() {
        beginMfaAction('regenerate');
    }

    function confirmRegenerate() {
        if (!confirmPassword) {
            setConfirmError('Please enter your password.');
            return;
        }
        if (!isFresh && !proofData.otp && !proofData.recovery_code) {
            setConfirmAction(null);
            setMfaAction('regenerate');
            setForceMfaProof(true);
            return;
        }
        setLoading(true);
        setConfirmError('');

        fetch(route('profile.mfa.recovery-codes.regenerate'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ password: confirmPassword }),
        })
        .then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const error = new Error(data.errors?.password?.[0] || data.errors?.mfa_policy?.[0] || data.message || 'Incorrect password.');
                error.mfaStale = Boolean(data.errors?.mfa_confirmation);
                error.mfaPolicy = Boolean(data.errors?.mfa_policy);
                throw error;
            }
            setRecoveryCodes(data.recovery_codes || []);
            setConfirmAction(null);
            setConfirmPassword('');
            toast.success('Recovery codes regenerated. Save them in a safe place.');
        })
        .catch((e) => {
            if (e.mfaStale) {
                setConfirmAction(null);
                setForceMfaProof(true);
                setMfaAction('regenerate');
            }
            setConfirmError(e.message || 'Failed to regenerate codes.');
            setConfirmPassword('');
            toast.error(e.message || 'Failed to regenerate codes.');
        })
        .finally(() => setLoading(false));
    }

    function cancelConfirm() {
        setConfirmAction(null);
        setConfirmPassword('');
        setConfirmError('');
    }

    function handleMfaConfirmed({ proof }) {
        const action = mfaAction;
        setMfaAction(null);
        setForceMfaProof(false);
        setProofData(action === 'disable' ? proof : {});
        if (action === 'disable') {
            setConfirmPassword('');
            setConfirmError('');
            setConfirmAction('disable');
        } else {
            askForPassword(action);
        }
    }

    function handleCopyCodes() {
        navigator.clipboard.writeText(recoveryCodes.join('\n'));
    }

    function handleDownloadCodes() {
        const blob = new Blob([recoveryCodes.join('\n')], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    }

    return (
        <>
        <CardSection title="Two-Factor Authentication">
            <div className="space-y-4" aria-live="polite">
                {error && <InputError message={error} className="mb-3" />}

                {!enabled && step === 'idle' && (
                    <div>
                        <p className="text-[13px] text-slate-600 mb-3">
                            Add an extra layer of security to your account using an authenticator app
                            like Google Authenticator, Microsoft Authenticator, or Authy.
                        </p>
                        <p className={`mb-3 text-[12px] font-medium ${required ? 'text-amber-700' : 'text-slate-500'}`}>
                            {required
                                ? 'Your role requires MFA. Enroll now to keep access to protected areas.'
                                : 'MFA is optional for your role. You can remove it later from this page.'}
                        </p>
                        <PrimaryButton onClick={handleEnable} disabled={loading}>
                            {loading ? 'Generating...' : 'Enable Two-Factor Authentication'}
                        </PrimaryButton>
                    </div>
                )}

                {step === 'show_qr' && (
                    <div className="space-y-4">
                        <p className="text-[13px] text-slate-600">
                            Scan this QR code with your authenticator app, then enter the 6-digit code below.
                        </p>
                        {qrUrl && (
                            <div className="flex justify-center">
                                <img src={qrUrl} alt="MFA QR Code" className="w-40 h-40 border border-slate-200 rounded-lg" />
                            </div>
                        )}
                        {secret && (
                            <div className="bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
                                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Manual Setup Key</p>
                                <p className="text-sm font-mono text-slate-800 break-all select-all">{secret}</p>
                            </div>
                        )}
                        <div>
                            <InputLabel htmlFor="mfa-otp" value="Authentication Code" />
                            <TextInput
                                id="mfa-otp"
                                className="mt-1 block w-full"
                                value={otp}
                                onChange={(e) => { setOtp(e.target.value.replace(/\D/g, '').slice(0, 6)); setError(''); }}
                                placeholder="000000"
                                maxLength={6}
                                autoComplete="off"
                            />
                            <InputError className="mt-2" message={error} />
                        </div>
                        <div className="flex items-center gap-2">
                            <PrimaryButton onClick={handleVerify} disabled={loading || otp.length !== 6}>
                                {loading ? 'Verifying...' : 'Verify & Enable'}
                            </PrimaryButton>
                            <SecondaryButton onClick={() => setStep('idle')} disabled={loading}>
                                Cancel
                            </SecondaryButton>
                        </div>
                    </div>
                )}

                {enabled && (step === 'recovery' || showRecovery) && (
                    <div className="space-y-4">
                        {step === 'recovery' && (
                            <div className="rounded-md bg-green-50 border border-green-200 p-3">
                                <p className="text-sm font-semibold text-green-800">Two-factor authentication is now enabled!</p>
                                <p className="text-[12px] text-green-700 mt-1">
                                    Save these recovery codes in a safe place. Each code can be used once to access your account
                                    if you lose access to your authenticator app.
                                </p>
                            </div>
                        )}
                        {recoveryCodes.length > 0 && (
                            <div>
                                <p className="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 mb-2">Recovery Codes</p>
                                <div className="grid grid-cols-2 gap-1.5">
                                    {recoveryCodes.map((code, i) => (
                                        <code key={i} className="block bg-slate-50 border border-slate-200 rounded px-2 py-1 text-[12px] font-mono text-slate-700">
                                            {code}
                                        </code>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 mt-3">
                                    <SecondaryButton onClick={handleCopyCodes} disabled={loading}>Copy Codes</SecondaryButton>
                                    <SecondaryButton onClick={handleDownloadCodes} disabled={loading}>Download</SecondaryButton>
                                    <SecondaryButton onClick={handleRegenerateCodes} disabled={loading}>Regenerate</SecondaryButton>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {enabled && step !== 'recovery' && !showRecovery && (
                    <div>
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-bold text-green-800">
                                <svg className="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                                Enabled
                            </span>
                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ${required ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                                {required ? 'Required by policy' : 'Optional'}
                            </span>
                        </div>
                        <p className="text-[13px] text-slate-600 mb-3">
                            {required
                                ? 'Your role requires MFA. It cannot be disabled here. If you lose access to your authenticator and recovery codes, contact an administrator.'
                                : 'Your account is protected with two-factor authentication. You can disable it if you no longer want it.'}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            {!required && (
                                <DangerButton onClick={handleDisable} disabled={loading}>
                                    {loading ? 'Disabling...' : 'Disable MFA'}
                                </DangerButton>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </CardSection>

        {/* Password confirmation dialog for Disable / Regenerate */}
        {confirmAction && (
            <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4" onClick={cancelConfirm}>
                <div className="w-full max-w-md rounded-lg owb-modal-animate bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                    <div className="flex flex-col items-center px-6 py-5 text-center">
                        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${confirmAction === 'disable' ? 'bg-red-50' : 'bg-blue-50'}`}>
                            <span className={`material-symbols-outlined text-[22px] ${confirmAction === 'disable' ? 'text-red-600' : 'text-blue-600'}`}>
                                {confirmAction === 'disable' ? 'warning' : 'key'}
                            </span>
                        </div>
                        <h2 className="mt-3 text-[16px] font-extrabold text-slate-900">
                            {confirmAction === 'disable' ? 'Disable Two-Factor Authentication' : 'Regenerate Recovery Codes'}
                        </h2>
                        <p className="mt-1.5 text-[13px] text-slate-600 leading-relaxed">
                            {confirmAction === 'disable'
                                ? 'Your account will be less secure without two-factor authentication. Enter your password to confirm.'
                                : 'Your existing recovery codes will stop working. Enter your password to generate new codes.'
                            }
                        </p>
                    </div>

                    <div className="px-6 pb-4">
                        <InputLabel htmlFor="confirm-mfa-password" value="Your Password" />
                        <TextInput
                            id="confirm-mfa-password"
                            type="password"
                            className="mt-1 block w-full"
                            value={confirmPassword}
                            onChange={(e) => { setConfirmPassword(e.target.value); setConfirmError(''); }}
                            placeholder="Enter your current password"
                            autoComplete="current-password"
                        />
                        {confirmError && <InputError message={confirmError} className="mt-2" />}
                    </div>

                    <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                        <button
                            onClick={cancelConfirm}
                            className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            onClick={confirmAction === 'disable' ? confirmDisable : confirmRegenerate}
                            disabled={loading || !confirmPassword}
                            className={`h-9 rounded-[3px] px-4 text-[12px] font-bold text-white transition-colors disabled:opacity-60 ${
                                confirmAction === 'disable'
                                    ? 'bg-red-600 hover:bg-red-700'
                                    : 'bg-blue-900 hover:bg-blue-800'
                            }`}
                        >
                            {loading ? 'Confirming...' : confirmAction === 'disable' ? 'Disable MFA' : 'Regenerate Codes'}
                        </button>
                    </div>
                </div>
            </div>
        )}

        <MfaConfirmDialog
            open={!!mfaAction}
            title={mfaAction === 'disable' ? 'Confirm MFA removal' : 'Confirm recovery-code regeneration'}
            description={
                mfaAction === 'disable'
                    ? 'Disabling MFA removes your authenticator and recovery codes. Confirm with a current code first.'
                    : 'Regenerating recovery codes invalidates the old ones. Confirm with a current code first.'
            }
            confirmLabel="Continue"
            confirmedUntil={status?.confirmed_until}
            forceProof={forceMfaProof}
            collectOnly={mfaAction === 'disable'}
            onCancel={() => setMfaAction(null)}
            onConfirmed={handleMfaConfirmed}
        />
        </>
    );
}
