import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import PasswordInput from '@/Components/PasswordInput';
import TurnstileWidget from '@/Components/TurnstileWidget';
import { makeRegisterSchema } from '@/Schemas/authSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import { getTurnstileError } from '@/lib/turnstile';

export default function Register({ initial = {} }) {
    const { passwordRules, turnstile } = usePage().props;
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileStatus, setTurnstileStatus] = useState('idle');
    const [emailStatus, setEmailStatus] = useState('unknown');
    const credentials = initial.credentials ?? {};
    const { data, setData, post, processing, errors } = useForm({
        email: credentials.email ?? '',
        password: '',
        password_confirmation: '',
        cf_turnstile_response: '',
    });
    const schema = useMemo(() => makeRegisterSchema(passwordRules), [passwordRules]);
    const { validate } = useClientValidation(schema, data, () => {});

    useEffect(() => {
        const email = data.email.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setEmailStatus('unknown');
            return undefined;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setEmailStatus('checking');
            axios.get(route('api.register.email-availability'), { params: { email }, signal: controller.signal })
                .then(({ data: response }) => setEmailStatus(response?.taken ? 'taken' : 'available'))
                .catch((error) => { if (!axios.isCancel?.(error)) setEmailStatus('unknown'); });
        }, 500);
        return () => { clearTimeout(timer); controller.abort(); };
    }, [data.email]);

    function submit(event) {
        event.preventDefault();
        if (emailStatus === 'taken' || !validate()) return;
        post(route('register.store'), {
            onError: () => {},
        });
    }

    const inputClass = 'w-full rounded-md border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none';

    return (
        <div className="min-h-dvh bg-surface font-body text-on-surface">
            <Head title="Create your account" />
            <AppHeader minimal />
            <main className="flex min-h-dvh flex-col pt-[72px]">
                <div className="flex flex-1 items-center justify-center px-4 py-10 lg:px-10">
                    <div className="w-full max-w-xl">
                        <div className="mb-8">
                            <span className="material-symbols-outlined mb-4 block text-[32px] text-primary" aria-hidden="true">person_add</span>
                            <h1 className="font-headline text-2xl font-bold text-slate-900">Create your account</h1>
                            <p className="mt-2 text-sm text-slate-600">Use your email and password to get started. We'll ask you to verify your email next.</p>
                        </div>
                        <form onSubmit={submit} className="space-y-5 rounded-lg border border-outline-variant bg-white p-5 sm:p-7">
                            <div>
                                <label htmlFor="register-email" className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email address</label>
                                <input id="register-email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} className={inputClass} autoComplete="email" autoFocus required aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'register-email-error' : undefined} />
                                {errors.email && <p id="register-email-error" className="mt-1 text-xs text-red-600">{errors.email}</p>}
                                {!errors.email && emailStatus === 'checking' && <p className="mt-1 text-xs text-slate-500" role="status">Checking email availability...</p>}
                                {emailStatus === 'taken' && <p className="mt-1 text-xs text-red-600" role="alert">This email is already registered. <Link href={route('login')} className="font-semibold underline">Sign in instead</Link>.</p>}
                            </div>
                            <PasswordInput id="register-password" label="Password" value={data.password} onChange={(value) => setData('password', value)} error={errors.password} autoComplete="new-password" showStrengthMeter rules={passwordRules} confirmation={data.password_confirmation} />
                            <PasswordInput id="register-password-confirm" label="Confirm password" value={data.password_confirmation} onChange={(value) => setData('password_confirmation', value)} error={errors.password_confirmation} autoComplete="new-password" />
                            {turnstile?.enabled && <div className="text-center"><TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} onStatusChange={setTurnstileStatus} />{errors.captcha && <p className="mt-1 text-xs text-red-600">{errors.captcha}</p>}{!turnstileToken && <p className={`mt-1 text-xs ${['error', 'expired'].includes(turnstileStatus) ? 'text-red-600' : 'text-slate-500'}`}>{getTurnstileError({ token: turnstileToken, status: turnstileStatus })}</p>}</div>}
                            <input type="hidden" name="cf_turnstile_response" value={turnstileToken} readOnly />
                            <button type="submit" disabled={processing || emailStatus === 'taken' || (turnstile?.enabled && !turnstileToken)} className="w-full rounded-md bg-primary px-8 py-4 text-sm font-bold text-on-primary shadow-xl transition hover:brightness-110 disabled:opacity-60">{processing ? 'Continuing...' : 'Continue to email verification'}</button>
                            <p className="text-center text-xs text-on-surface-variant"><Link href={route('login')} className="font-bold hover:text-primary">Already have an account? Sign in</Link></p>
                        </form>
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
    );
}
