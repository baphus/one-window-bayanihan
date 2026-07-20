import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

export default function RegisterViaInvite({ invite }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        password: '',
        password_confirmation: '',
        position: '',
        department: '',
        contact_number: '',
    });

    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        post(route('register-via-invite.store', invite.token));
    };

    // Password strength calculation
    const getPasswordStrength = () => {
        const pw = data.password;
        if (!pw) return { score: 0, label: '', color: '' };
        let score = 0;
        if (pw.length >= 8) score++;
        if (pw.length >= 12) score++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;

        if (score <= 2) return { score, label: 'Weak', color: 'bg-red-500' };
        if (score <= 3) return { score, label: 'Fair', color: 'bg-amber-500' };
        if (score <= 4) return { score, label: 'Good', color: 'bg-blue-500' };
        return { score, label: 'Strong', color: 'bg-green-500' };
    };

    const strength = getPasswordStrength();

    const roleLabels = {
        ADMIN: 'System Admin',
        CASE_MANAGER: 'Case Manager',
        AGENCY: 'Agency Focal',
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Complete Registration" />
            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">
                    {/* Left: Branding (same as Login) */}
                    <div className="lg:w-[60%] relative min-h-[320px] lg:min-h-full flex flex-col justify-center text-white overflow-hidden">
                        <div className="absolute inset-0 z-0">
                            <img src="/images/auth/login-bg.jpg" alt="" className="h-full w-full object-cover" />
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
                                Complete your registration to get started.
                            </p>
                        </div>
                    </div>

                    {/* Right: Registration Form */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">person_add</span>
                                <h2 className="font-headline text-2xl font-bold text-slate-900">Complete Your Registration</h2>
                                <p className="mt-2 text-sm text-slate-500">Set up your account details to get started.</p>
                            </div>

                            {/* Email (read-only) */}
                            <div className="mb-4">
                                <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email Address</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">lock</span>
                                    <input
                                        type="email"
                                        value={invite.email}
                                        readOnly
                                        className="w-full border border-outline-variant bg-surface-container/50 px-4 py-3 pl-12 text-sm text-on-surface-variant/70 cursor-not-allowed rounded-none"
                                    />
                                </div>
                            </div>

                            {/* Role + Agency badges */}
                            <div className="flex gap-2 mb-6">
                                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border ${
                                    invite.role === 'ADMIN' ? 'bg-purple-100 text-purple-800 border-purple-300' :
                                    invite.role === 'CASE_MANAGER' ? 'bg-blue-100 text-blue-800 border-blue-300' :
                                    'bg-amber-100 text-amber-800 border-amber-300'
                                }`}>
                                    {roleLabels[invite.role] || invite.role}
                                </span>
                                {invite.agency && (
                                    <span className="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold border bg-slate-100 text-slate-700 border-slate-300">
                                        {invite.agency.name}
                                    </span>
                                )}
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                {/* Name */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Full Name</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-none"
                                        required
                                    />
                                    {errors.name && <p className="mt-1 text-xs font-semibold text-error">{errors.name}</p>}
                                </div>

                                {/* Password */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Password</label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 text-[20px] material-symbols-outlined">lock</span>
                                        <input
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="w-full border border-outline-variant bg-surface-container px-4 py-3 pl-12 pr-12 text-sm focus:border-primary focus:outline-none rounded-none"
                                            required
                                        />
                                        <button type="button" onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary">
                                            <span className="material-symbols-outlined text-[20px]">{showPassword ? 'visibility_off' : 'visibility'}</span>
                                        </button>
                                    </div>
                                    {errors.password && <p className="mt-1 text-xs font-semibold text-error">{errors.password}</p>}

                                    {/* Strength indicator */}
                                    {data.password && (
                                        <div className="mt-2">
                                            <div className="flex gap-1 mb-1">
                                                {[1, 2, 3, 4, 5].map((i) => (
                                                    <div key={i} className={`h-1 flex-1 rounded-full ${i <= strength.score ? strength.color : 'bg-slate-200'}`} />
                                                ))}
                                            </div>
                                            <p className="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">{strength.label}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Password Confirmation */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">Confirm Password</label>
                                    <input
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        className="w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-none"
                                        required
                                    />
                                    {errors.password_confirmation && <p className="mt-1 text-xs font-semibold text-error">{errors.password_confirmation}</p>}
                                </div>

                                {/* Optional fields */}
                                <div className="border-t border-outline-variant pt-5 mt-5">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant mb-4">Optional Profile Details</p>

                                    <div className="space-y-4">
                                        <div>
                                            <label className="mb-1 block text-xs font-bold text-on-surface-variant">Position</label>
                                            <input type="text" value={data.position} onChange={(e) => setData('position', e.target.value)}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:border-primary focus:outline-none rounded-none" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-bold text-on-surface-variant">Department</label>
                                            <input type="text" value={data.department} onChange={(e) => setData('department', e.target.value)}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:border-primary focus:outline-none rounded-none" />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs font-bold text-on-surface-variant">Contact Number</label>
                                            <input type="text" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)}
                                                className="w-full border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:border-primary focus:outline-none rounded-none" />
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" disabled={processing}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none">
                                    {processing ? 'Creating Account...' : 'Complete Registration'}
                                </button>
                            </form>

                            <p className="mt-6 text-center text-sm text-slate-500">
                                Already have an account?{' '}
                                <Link href={route('login')} className="font-bold text-primary hover:underline">Sign in</Link>
                            </p>
                        </div>
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
    );
}
