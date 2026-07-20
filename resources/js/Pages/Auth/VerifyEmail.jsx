import { Head, Link, useForm } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Email Verification" />
            <AppHeader minimal />

            <main className="min-h-dvh pt-[72px] flex flex-col">
                <div className="flex-1 flex flex-col lg:flex-row">
                    {/* Left: Branding */}
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
                                Please verify your email address to continue.
                            </p>
                        </div>
                    </div>

                    {/* Right: Form */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">mark_email_read</span>
                                <h2 className="font-headline text-2xl font-bold text-slate-900">Verify Your Email</h2>
                                <p className="mt-2 text-sm text-slate-500">
                                    Before getting started, please verify your email address by clicking the link we sent you.
                                </p>
                            </div>

                            {status === 'verification-link-sent' && (
                                <div className="bg-green-50 p-4 border border-green-200 flex items-center gap-3 mb-6">
                                    <span className="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
                                    <p className="text-xs font-semibold text-green-800">
                                        A new verification link has been sent to your email address.
                                    </p>
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-6">
                                <button type="submit" disabled={processing}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-none">
                                    {processing ? 'Sending...' : 'Resend Verification Email'}
                                </button>
                            </form>

                            <div className="mt-6 text-center">
                                <Link href={route('logout')} method="post" as="button"
                                    className="text-sm font-bold text-on-surface-variant hover:text-primary transition-colors">
                                    Log Out
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
            <AppFooter />
        </div>
    );
}
