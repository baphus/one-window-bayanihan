import { Head, Link, useForm } from '@inertiajs/react';
import AppFooter from '@/Components/landing/AppFooter';
import AppHeader from '@/Components/landing/AppHeader';

const ROLE_CARDS = [
    {
        value: 'OFW',
        icon: 'flight',
        title: 'I am an OFW',
        description: 'File and track your own case request.',
    },
    {
        value: 'NEXT_OF_KIN',
        icon: 'family_restroom',
        title: 'Filing for a family member',
        description: 'You are filing on behalf of an OFW.',
    },
];

function FieldError({ message }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-red-600">{message}</p>;
}

export default function RegisterProfile() {
    const { data, setData, post, processing, errors } = useForm({
        type: 'OFW',
        otp: '',
        first_name: '',
        last_name: '',
        middle_name: '',
        suffix: '',
        date_of_birth: '',
        sex: '',
        contact_number: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register.profile.store'));
    };

    const inputClass =
        'w-full border border-outline-variant bg-surface-container px-4 py-3 text-sm focus:border-primary focus:outline-none rounded-md';

    return (
        <div className="min-h-dvh bg-gradient-to-br from-primary via-primary/95 to-primary-container/30 font-body text-on-surface">
            <Head title="Complete your profile" />

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
                                    badge
                                </span>

                                <h2 className="font-headline text-2xl font-bold text-slate-900">
                                    Complete your profile
                                </h2>

                                <p className="mt-2 text-sm text-slate-500">
                                    Step 2 of 2. Almost done! Fill in your profile to continue.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                {/* Who is this account for */}
                                <div>
                                    <span className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Who is this account for?
                                    </span>
                                    <div className="grid grid-cols-2 gap-3">
                                        {ROLE_CARDS.map((role) => {
                                            const selected = data.type === role.value;
                                            return (
                                                <button
                                                    key={role.value}
                                                    type="button"
                                                    onClick={() => setData('type', role.value)}
                                                    aria-pressed={selected}
                                                    className={`rounded-md border p-4 text-left transition-all ${
                                                        selected
                                                            ? 'border-primary bg-primary-fixed/40 ring-1 ring-primary'
                                                            : 'border-outline-variant bg-surface-container hover:border-primary/50'
                                                    }`}
                                                >
                                                    <span className={`material-symbols-outlined block text-[24px] ${selected ? 'text-primary' : 'text-slate-400'}`}>
                                                        {role.icon}
                                                    </span>
                                                    <span className="mt-2 block text-sm font-bold text-slate-900">
                                                        {role.title}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-slate-500">
                                                        {role.description}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <FieldError message={errors.type} />
                                </div>

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
                                            className={inputClass}
                                            autoComplete="given-name"
                                            required
                                            maxLength={255}
                                        />
                                        <FieldError message={errors.first_name} />
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Last Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                            className={inputClass}
                                            autoComplete="family-name"
                                            required
                                            maxLength={255}
                                        />
                                        <FieldError message={errors.last_name} />
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Middle Name
                                        </label>
                                        <input
                                            type="text"
                                            value={data.middle_name}
                                            onChange={(e) => setData('middle_name', e.target.value)}
                                            className={inputClass}
                                            maxLength={255}
                                        />
                                        <FieldError message={errors.middle_name} />
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Suffix
                                        </label>
                                        <input
                                            type="text"
                                            value={data.suffix}
                                            onChange={(e) => setData('suffix', e.target.value)}
                                            placeholder="Jr, Sr, III"
                                            className={inputClass}
                                            maxLength={50}
                                        />
                                        <FieldError message={errors.suffix} />
                                    </div>
                                </div>

                                {/* Birth + sex */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Date of Birth
                                        </label>
                                        <input
                                            type="date"
                                            value={data.date_of_birth}
                                            onChange={(e) => setData('date_of_birth', e.target.value)}
                                            className={inputClass}
                                        />
                                        <FieldError message={errors.date_of_birth} />
                                    </div>
                                    <div>
                                        <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            Sex
                                        </label>
                                        <select
                                            value={data.sex}
                                            onChange={(e) => setData('sex', e.target.value)}
                                            className={inputClass}
                                        >
                                            <option value="">Select…</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                        <FieldError message={errors.sex} />
                                    </div>
                                </div>

                                {/* Contact */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Contact Number
                                    </label>
                                    <input
                                        type="tel"
                                        value={data.contact_number}
                                        onChange={(e) => setData('contact_number', e.target.value)}
                                        placeholder="09xx xxx xxxx"
                                        className={inputClass}
                                        autoComplete="tel"
                                        maxLength={50}
                                    />
                                    <FieldError message={errors.contact_number} />
                                </div>

                                {/* Email verification code */}
                                <div>
                                    <label className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        Email Verification Code
                                    </label>
                                    <input
                                        type="text"
                                        value={data.otp}
                                        onChange={(e) => setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                        placeholder="6-digit code sent to your email"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        className={`${inputClass} tracking-[0.3em] text-center font-bold`}
                                        required
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        Enter the 6-digit code we sent to your email address.
                                    </p>
                                    <FieldError message={errors.otp} />
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary text-on-primary px-8 py-4 text-sm font-bold shadow-xl hover:brightness-110 active:scale-95 transition-all disabled:opacity-60 rounded-md"
                                >
                                    {processing ? 'Finishing…' : 'Finish and Go to Dashboard'}
                                </button>

                                <div className="text-center">
                                    <Link
                                        href={route('register')}
                                        className="text-xs font-bold text-on-surface-variant hover:text-primary transition-colors"
                                    >
                                        Back to account details
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
