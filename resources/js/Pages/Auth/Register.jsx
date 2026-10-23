import { useState, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import PasswordInput from '@/Components/PasswordInput';
import TurnstileWidget from '@/Components/TurnstileWidget';
import { makeRegisterSchema } from '@/Schemas/authSchemas';
import useClientValidation from '@/Hooks/useClientValidation';
import { getTurnstileError } from '@/lib/turnstile';

export default function Register() {
    const { passwordRules, turnstile } = usePage().props;
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileStatus, setTurnstileStatus] = useState('idle');

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
        first_name: '',
        middle_name: '',
        last_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        cf_turnstile_response: '',
    });

    const schema = useMemo(() => makeRegisterSchema(passwordRules), [passwordRules]);
    const { validate } = useClientValidation(schema, data, setError);

    const submit = (e) => {
        e.preventDefault();
        clearErrors();
        if (!validate()) return;

        setData('cf_turnstile_response', turnstileToken);
        post(route('register'), {
            onFinish: () => { reset('password', 'password_confirmation'); },
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Register" />

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">

                    {/* Left: Branding */}
                    <div className="lg:w-[60%] relative min-h-[320px] lg:min-h-full lg:sticky lg:top-[72px] flex flex-col justify-center text-white overflow-hidden bg-[#01010d]">
                        <div className="absolute inset-0 z-0">
                            <img
                                src="/images/auth/login-bg.jpg"
                                alt=""
                                className="h-full w-full object-cover"
                            />
                            <div className="absolute inset-0 bg-primary/85 mix-blend-multiply" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-primary/40 to-transparent" />
                        </div>

                        <div className="relative z-10 p-10 lg:p-14">
                            <div className="mb-8">
                                <img src="/logo.png" alt="One Window Bayanihan Logo" className="h-14 w-14 object-contain" />
                            </div>

                            <h1 className="mb-2 font-serif text-3xl lg:text-4xl font-bold leading-tight tracking-tight">
                                One Window Bayanihan
                            </h1>
                            <p className="mb-6 font-serif text-xl lg:text-2xl uppercase tracking-widest text-white/70">
                                Assistance Program
                            </p>

                            <div className="h-1 w-16 bg-secondary-container mb-8" />

                            <p className="max-w-xs text-base text-white/80 leading-relaxed font-medium">
                                Connecting government agencies for seamless migrant worker assistance across Region VII.
                            </p>
                        </div>
                    </div>

                    {/* Right: Form — min-h mirrors profile Step 1 content height (851px) so both steps match */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 lg:min-h-[max(851px,calc(100dvh-72px))] bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    person_add
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Create your account
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Step 1 of 2 — set up your login.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                {/* Name */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            First Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-md"
                                            autoComplete="given-name"
                                            autoFocus
                                            required
                                            maxLength={255}
                                        />
                                        {errors.first_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.first_name}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Last Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-md"
                                            autoComplete="family-name"
                                            required
                                            maxLength={255}
                                        />
                                        {errors.last_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.last_name}</p>
                                        )}
                                    </div>
                                    <div className="col-span-2">
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Middle Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.middle_name}
                                            onChange={(e) => setData('middle_name', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-md"
                                            autoComplete="additional-name"
                                            maxLength={255}
                                        />
                                        {errors.middle_name && (
                                            <p className="mt-1 text-xs text-red-600">{errors.middle_name}</p>
                                        )}
                                    </div>
                                </div>

                                {/* Email */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Email Address
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                            alternate_email
                                        </span>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 text-sm focus:border-primary focus:outline-none rounded-md"
                                            autoComplete="username"
                                            required
                                        />
                                    </div>
                                    {errors.email && (
                                        <p className="mt-1 text-xs text-red-600">{errors.email}</p>
                                    )}
                                </div>

                                {/* Password */}
                                <PasswordInput
                                    id="register-password"
                                    label="Password"
                                    value={data.password}
                                    onChange={(v) => setData('password', v)}
                                    error={errors.password}
                                    autoComplete="new-password"
                                    showStrengthMeter
                                    rules={passwordRules}
                                    confirmation={data.password_confirmation}
                                />

                                {/* Confirm Password */}
                                <PasswordInput
                                    id="register-password-confirm"
                                    label="Confirm Password"
                                    value={data.password_confirmation}
                                    onChange={(v) => setData('password_confirmation', v)}
                                    error={errors.password_confirmation}
                                    autoComplete="new-password"
                                />

                                {turnstile?.enabled && (
                                    <div className="text-center">
                                        <TurnstileWidget
                                            onToken={setTurnstileToken}
                                            onExpire={() => setTurnstileToken('')}
                                            onStatusChange={setTurnstileStatus}
                                        />
                                        {errors.captcha && (
                                            <p className="mt-1 text-xs text-red-600">{errors.captcha}</p>
                                        )}
                                        {!turnstileToken && (
                                            <p className={`mt-1 text-xs ${turnstileStatus === 'error' || turnstileStatus === 'expired' ? 'text-red-600' : 'text-slate-500'}`}>
                                                {getTurnstileError({ token: turnstileToken, status: turnstileStatus })}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing || (turnstile?.enabled && !turnstileToken)}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-md"
                                >
                                    {processing ? 'Creating Account...' : 'Create Account'}
                                </button>

                                <div className="text-center">
                                    <Link
                                        href={route('login')}
                                        className="text-xs font-bold text-on-surface-variant hover:text-primary transition-colors"
                                    >
                                        Already have an account? Sign in
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
