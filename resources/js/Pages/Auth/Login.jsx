import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen flex-col bg-surface font-body text-on-surface">
            <Head title="Log in" />

            <nav className="w-full border-b border-outline-variant bg-surface-bright">
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
                    <Link href={route('register')} className="font-label text-sm font-semibold text-primary hover:underline">
                        Create Account
                    </Link>
                </div>
            </nav>

            <main className="flex flex-1 items-center justify-center px-4 py-12">
                <div className="w-full max-w-[420px]">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <span className="material-symbols-outlined text-3xl text-primary" style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}>lock</span>
                        </div>
                        <h1 className="font-headline text-2xl font-extrabold text-on-surface">Welcome back</h1>
                        <p className="mt-1 font-body text-sm text-on-surface-variant">Sign in to your account to continue</p>
                    </div>

                    <div className="rounded-xl border border-outline-variant bg-surface-bright p-8 shadow-sm">
                        {status && (
                            <div className="mb-4 rounded-lg bg-primary-fixed px-4 py-3 font-body text-sm font-medium text-primary">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel htmlFor="email" value="Email address" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="mt-1.5 block w-full"
                                    autoComplete="username"
                                    isFocused={true}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} className="mt-2" />
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <InputLabel htmlFor="password" value="Password" />
                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="font-label text-xs font-semibold text-primary hover:underline"
                                        >
                                            Forgot password?
                                        </Link>
                                    )}
                                </div>
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="mt-1.5 block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                <InputError message={errors.password} className="mt-2" />
                            </div>

                            <div>
                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        name="remember"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                    />
                                    <span className="font-body text-sm text-on-surface">Remember me</span>
                                </label>
                            </div>

                            <PrimaryButton className="w-full justify-center" disabled={processing}>
                                Sign in
                            </PrimaryButton>
                        </form>
                    </div>

                    <div className="mt-6 rounded-lg border border-dashed border-outline-variant bg-surface-container-low p-4">
                        <p className="font-label text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-2">Demo Credentials</p>
                        <div className="space-y-1 font-body text-xs text-on-surface-variant">
                            <p><span className="font-semibold text-on-surface">Case Manager:</span> case@bayanihan.gov.ph / password</p>
                            <p><span className="font-semibold text-on-surface">Admin:</span> admin@bayanihan.gov.ph / password</p>
                            <p><span className="font-semibold text-on-surface">Agency:</span> {"{slug}"}@bayanihan.gov.ph / password (e.g. owwa@bayanihan.gov.ph)</p>
                        </div>
                    </div>
                </div>
            </main>

            <footer className="border-t border-outline-variant bg-surface-bright px-8 py-4">
                <p className="text-center font-body text-xs text-on-surface-variant">&copy; 2026 Bayanihan One Window System. All Rights Reserved.</p>
            </footer>
        </div>
    );
}
