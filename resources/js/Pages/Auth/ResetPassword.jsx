import { useState } from 'react';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';
import { Head, useForm } from '@inertiajs/react';
import { resetPasswordSchema } from '@/Schemas/authSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function ResetPassword({ token, email }) {
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors, setError } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const { validate } = useClientValidation(resetPasswordSchema, data, setError);

    const submit = (e) => {
        e.preventDefault();
        clearErrors();
        if (!validate()) return;

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Reset Password" />

            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">

                    {/* Left: Branding */}
                    <div className="lg:w-[60%] relative min-h-[320px] lg:min-h-full flex flex-col justify-center text-white overflow-hidden">
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

                    {/* Right: Form */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    lock
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Reset Password
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Create a new password for your account.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
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
                                            readOnly
                                            className="w-full border border-outline-variant bg-surface-container/50 px-4 py-3 pl-12 text-sm text-on-surface-variant/70 cursor-not-allowed rounded-none"
                                        />
                                    </div>
                                    <InputError message={errors.email} className="mt-2" />
                                </div>

                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        New Password
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                            lock
                                        </span>
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                            autoComplete="new-password"
                                            autoFocus
                                            required
                                            minLength={8}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary"
                                        >
                                            <span className="material-symbols-outlined text-[20px]">
                                                {showPassword ? 'visibility_off' : 'visibility'}
                                            </span>
                                        </button>
                                    </div>
                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Confirm Password
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">
                                            lock
                                        </span>
                                        <input
                                            type={showConfirmPassword ? 'text' : 'password'}
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                            autoComplete="new-password"
                                            required
                                            minLength={8}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary"
                                        >
                                            <span className="material-symbols-outlined text-[20px]">
                                                {showConfirmPassword ? 'visibility_off' : 'visibility'}
                                            </span>
                                        </button>
                                    </div>
                                    <InputError message={errors.password_confirmation} className="mt-2" />
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none"
                                >
                                    {processing ? 'Resetting...' : 'Reset Password'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
