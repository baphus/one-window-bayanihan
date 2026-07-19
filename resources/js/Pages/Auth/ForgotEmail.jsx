import { Head, Link } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

export default function ForgotEmail() {
    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Forgot Email" />

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

                    {/* Right: Content */}
                    <div className="lg:w-[40%] flex items-center justify-center p-10 lg:p-14 bg-surface">
                        <div className="w-full max-w-md">
                            <div className="mb-8 text-center">
                                <span className="material-symbols-outlined mb-4 block text-primary text-[32px]">
                                    mail_lock
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Forgot Email
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Need help recovering your email address?
                                </p>
                            </div>

                            <div className="bg-slate-50 p-6 border border-outline-variant">
                                <p className="text-sm text-slate-600 leading-relaxed">
                                    Please contact your system administrator to retrieve your email address. They can look up your account and help you regain access.
                                </p>
                            </div>

                            <Link
                                href={route('login')}
                                className="mt-8 flex items-center justify-center gap-2 text-sm font-bold text-slate-500 hover:text-primary mx-auto transition-colors"
                            >
                                <span className="material-symbols-outlined text-[18px]">arrow_back</span>
                                Return to Sign In
                            </Link>
                        </div>
                    </div>
                </div>
            </main>

            <AppFooter />
        </div>
    );
}
