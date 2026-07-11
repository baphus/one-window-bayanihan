import { Head, Link } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';

export default function ForgotEmail() {
    return (
        <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
            <Head title="Forgot Email" />

            <AppHeader minimal />

            <main className="flex-1 flex flex-col pt-[72px]">
                <div className="bg-primary pt-16 pb-32">
                    <div className="absolute inset-0 z-0 pointer-events-none overflow-hidden">
                        <div className="absolute inset-0 bg-gradient-to-br from-primary via-primary/95 to-primary-container/30" />
                    </div>
                </div>

                <div className="relative z-10 mx-auto -mt-32 mb-24 w-full max-w-6xl px-4 lg:px-8">
                    <div className="flex flex-col lg:flex-row rounded-lg shadow-2xl bg-surface border border-slate-200 overflow-hidden">

                        {/* Left: Branding */}
                        <div className="lg:w-2/5 relative min-h-[500px] flex flex-col justify-center text-white overflow-hidden">
                            <div className="absolute inset-0 z-0">
                                <img
                                    src="https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&q=80"
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                                <div className="absolute inset-0 bg-primary/85 mix-blend-multiply" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-primary/40 to-transparent" />
                            </div>

                            <div className="relative z-10 p-10 lg:p-14">
                                <div className="mb-8">
                                    <img src="/logo.png" alt="Bayanihan Logo" className="h-14 w-14 object-contain" />
                                </div>

                                <h1 className="mb-6 font-headline text-2xl lg:text-3xl font-black leading-tight tracking-tight uppercase">
                                    Bayanihan<br />One Window
                                </h1>

                                <div className="h-1 w-16 bg-secondary-container mb-8" />

                                <p className="max-w-xs text-base text-white/80 leading-relaxed font-medium">
                                    Connecting government agencies for seamless migrant worker assistance across Region VII.
                                </p>
                            </div>
                        </div>

                        {/* Right: Content */}
                        <div className="lg:w-3/5 p-10 lg:p-14 bg-surface">
                            <div className="max-w-md mx-auto">
                                <div className="mb-8 flex items-center gap-3 border-b border-slate-200 pb-4">
                                    <span className="material-symbols-outlined text-primary text-2xl">mail_lock</span>
                                    <h2 className="font-headline text-xl font-bold">Forgot Email</h2>
                                </div>

                                <div className="bg-surface-container p-6 border border-outline-variant">
                                    <p className="text-sm text-on-surface-variant leading-relaxed">
                                        Please contact your system administrator to retrieve your email address.
                                    </p>
                                </div>

                                <Link
                                    href={route('login')}
                                    className="mt-8 flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors mx-auto w-fit"
                                >
                                    &larr; Back to Sign In
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

function AppHeader({ minimal }) {
    return (
        <nav className="fixed top-0 z-50 w-full border-b border-outline-variant bg-surface-bright">
            <div className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-4 md:px-8">
                <Link href="/" className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-outline-variant bg-surface-bright">
                        <span className="material-symbols-outlined text-2xl text-primary">handshake</span>
                    </div>
                    <div className="flex flex-col">
                        <span className="font-headline text-[18px] font-bold text-primary">Bayanihan One Window</span>
                        <span className="font-label text-[12px] font-medium uppercase tracking-wide text-on-surface-variant">DMW Region VII</span>
                    </div>
                </Link>
                {!minimal && (
                    <div className="hidden items-center gap-8 md:flex">
                        <Link href="/" className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Home</Link>
                        <Link href={route('track.index')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Track Your Case</Link>
                        <Link href={route('partners')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Partners</Link>
                        <Link href={route('contact')} className="font-label text-[14px] font-medium text-on-surface-variant transition-colors duration-200 hover:text-primary">Contact</Link>
                    </div>
                )}
                <Link href={route('login')}>
                    <button className="font-label text-sm font-semibold text-primary hover:underline bg-transparent border-none cursor-pointer">Sign In</button>
                </Link>
            </div>
        </nav>
    );
}
