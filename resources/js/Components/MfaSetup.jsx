import { useState } from 'react';
import { CardSection } from '@/Components/ui/CardSection';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { useToast } from '@/Hooks/useToast';
import TextInput from '@/Components/TextInput';

export default function MfaSetup({ mfaEnabled }) {
    const [enabled, setEnabled] = useState(mfaEnabled);
    const [step, setStep] = useState('idle'); // idle | show_qr | verifying | enabled | recovery
    const [secret, setSecret] = useState('');
    const [qrUrl, setQrUrl] = useState('');
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState([]);
    const [showRecovery, setShowRecovery] = useState(false);
    const toast = useToast();
    const [loading, setLoading] = useState(false);

    function handleEnable() {
        setLoading(true);
        setError('');

        fetch(route('profile.mfa.generate'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
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
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp }),
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) {
                setError(data.message || 'Invalid code. Please try again.');
                toast.error(data.message || 'Invalid code. Please try again.');
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
        if (!confirm('Are you sure you want to disable two-factor authentication?')) return;
        setLoading(true);
        setError('');

        fetch(route('profile.mfa.disable'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed to disable MFA.');
            setEnabled(false);
            setStep('idle');
            setSecret('');
            setQrUrl('');
            setOtp('');
            setRecoveryCodes([]);
            setShowRecovery(false);
            toast.success('Two-factor authentication disabled.');
        })
        .catch((e) => { console.error('MFA setup failed:', e); setError('An error occurred. Please try again.'); toast.error('An error occurred. Please try again.'); })
        .finally(() => setLoading(false));
    }

    function handleRegenerateCodes() {
        setLoading(true);
        fetch(route('profile.mfa.recovery-codes.regenerate'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(data => setRecoveryCodes(data.recovery_codes || []))
        .catch(() => { setError('Failed to regenerate codes.'); toast.error('Failed to regenerate codes.'); })
        .finally(() => setLoading(false));
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
        <CardSection title="Two-Factor Authentication">
            <div className="space-y-4" aria-live="polite">
                {error && <InputError message={error} className="mb-3" />}

                {!enabled && step === 'idle' && (
                    <div>
                        <p className="text-[13px] text-slate-600 mb-3">
                            Add an extra layer of security to your account using an authenticator app
                            like Google Authenticator, Microsoft Authenticator, or Authy.
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
                        <div className="flex items-center gap-2 mb-3">
                            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-bold text-green-800">
                                <svg className="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                                Enabled
                            </span>
                        </div>
                        <p className="text-[13px] text-slate-600 mb-3">
                            Your account is protected with two-factor authentication.
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <SecondaryButton onClick={() => { setShowRecovery(true); fetch(route('profile.mfa.recovery-codes')).then(r => r.json()).then(d => setRecoveryCodes(d.recovery_codes || [])).catch(() => {}); }}>
                                View Recovery Codes
                            </SecondaryButton>
                            <DangerButton onClick={handleDisable} disabled={loading}>
                                {loading ? 'Disabling...' : 'Disable MFA'}
                            </DangerButton>
                        </div>
                    </div>
                )}
            </div>
        </CardSection>
    );
}
