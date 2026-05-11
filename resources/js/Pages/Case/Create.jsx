import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect } from 'react';

const STEPS = [
    { id: 1, title: 'Case Setup', description: 'Define case parameters and tracking' },
    { id: 2, title: 'Client Profile', description: 'Enter client information' },
    { id: 3, title: 'Case Narrative', description: 'Document client situation' },
];

const SUFFIX_OPTIONS = ['', 'Jr', 'Sr', 'II', 'III', 'IV', 'V'];

function GenerateCaseId() {
    const now = new Date();
    const y = now.getFullYear();
    const m = `${now.getMonth() + 1}`.padStart(2, '0');
    const d = `${now.getDate()}`.padStart(2, '0');
    const s = `${Math.floor(Math.random() * 9000) + 1000}`;
    return `CM-${y}${m}${d}-${s}`;
}

function GenerateTrackingId() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let token = '';
    for (let i = 0; i < 7; i++) token += chars[Math.floor(Math.random() * chars.length)];
    return `OW-${token}`;
}

function Field({ label, required, children, className }) {
    return (
        <div className={className}>
            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}{required ? ' *' : ''}
            </label>
            {children}
        </div>
    );
}

function Subsection({ title, children }) {
    return (
        <div className="space-y-2.5">
            <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-[#334155]">{title}</h4>
            {children}
        </div>
    );
}

function Input({ value, onChange, placeholder, type = 'text', maxLength, readOnly, className = '' }) {
    return (
        <input
            type={type}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            maxLength={maxLength}
            readOnly={readOnly}
            className={`h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 ${readOnly ? 'bg-slate-50' : ''} ${className}`}
        />
    );
}

function Select({ value, onChange, options, placeholder }) {
    return (
        <select
            value={value}
            onChange={onChange}
            className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        >
            {placeholder && <option value="">{placeholder}</option>}
            {options.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
        </select>
    );
}

export default function CaseCreate() {
    const { data, setData, post, processing, errors } = useForm({
        client_type: 'OFW',
        summary: '',
        client: {
            first_name: '',
            last_name: '',
            middle_name: '',
            suffix: '',
            date_of_birth: '',
            sex: '',
            email: '',
            contact: '',
        },
        address: {
            line1: '',
            line2: '',
            city: '',
            province: '',
            postal_code: '',
            country: 'Philippines',
        },
        employment: {
            employer_name: '',
            position: '',
            country: '',
            start_date: '',
            end_date: '',
        },
        next_of_kin: {
            first_name: '',
            last_name: '',
            relationship: '',
            contact: '',
        },
        consent: false,
    });

    const [currentStep, setCurrentStep] = useState(1);
    const [caseId] = useState(() => GenerateCaseId());
    const [trackingId] = useState(() => GenerateTrackingId());
    const [clientSource, setClientSource] = useState('new');
    const [hasNextOfKin, setHasNextOfKin] = useState(true);
    const [nokFirstName, setNokFirstName] = useState('');
    const [nokLastName, setNokLastName] = useState('');
    const [nokContact, setNokContact] = useState('');
    const [nokRelationship, setNokRelationship] = useState('');
    const [clientGender, setClientGender] = useState('Male');
    const [clientEmail, setClientEmail] = useState('');
    const [clientContact, setClientContact] = useState('');
    const [lastCountry, setLastCountry] = useState('');
    const [lastJob, setLastJob] = useState('');
    const [arrivalDate, setArrivalDate] = useState('');
    const [consent, setConsent] = useState(false);

    const stepProgress = Math.round((currentStep / STEPS.length) * 100);

    function handleClientChange(field, value) {
        setData('client', { ...data.client, [field]: value });
    }

    function handleAddressChange(field, value) {
        setData('address', { ...data.address, [field]: value });
    }

    function handleEmploymentChange(field, value) {
        setData('employment', { ...data.employment, [field]: value });
    }

    function handleNext() {
        if (currentStep < 3) setCurrentStep((prev) => prev + 1);
    }

    function handleBack() {
        if (currentStep > 1) setCurrentStep((prev) => prev - 1);
    }

    function canProceed() {
        if (currentStep === 1) return true;
        if (currentStep === 2) {
            return data.client.first_name.trim().length > 0 && data.client.last_name.trim().length > 0;
        }
        return true;
    }

    function canSubmit() {
        return data.client.first_name.trim().length > 0
            && data.client.last_name.trim().length > 0
            && (clientSource === 'existing' || consent);
    }

    function handleSubmit(e) {
        e.preventDefault();
        setData('client', {
            ...data.client,
            sex: clientGender,
            email: clientEmail,
            contact: clientContact,
        });
        setData('consent', consent);
        if (hasNextOfKin) {
            setData('next_of_kin', {
                first_name: nokFirstName,
                last_name: nokLastName,
                relationship: nokRelationship,
                contact: nokContact,
            });
        }
        post(route('cases.store'), {
            onSuccess: () => { },
        });
    }

    const nokSummary = hasNextOfKin
        ? [nokFirstName, nokLastName].filter(Boolean).join(' ') || 'Not yet provided'
        : 'No next of kin indicated';

    return (
        <AppLayout title="Create New Case">
            <Head title="Create New Case" />

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Create New Case</h1>
                        <p className="text-sm text-slate-500 mt-1">A guided onboarding flow to register the case with confidence.</p>
                    </div>
                    <Link href={route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Cases</Link>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <section className="mx-auto flex max-w-6xl overflow-visible rounded-xl border border-[#cbd5e1] bg-white shadow-sm">
                    <div className="w-1/3 min-w-[280px] max-w-[320px] shrink-0 border-r border-[#cbd5e1] bg-slate-50/60 p-8">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Step Guide</h3>
                            <p className="mt-2 text-[14px] font-bold text-slate-800">Step {currentStep} of {STEPS.length}</p>
                            <p className="mt-1 text-[12px] text-slate-500">Estimated time: 3-5 minutes</p>
                            <div className="mt-4 h-2 w-full rounded-full bg-slate-100">
                                <div className="h-2 rounded-full bg-indigo-600 transition-all" style={{ width: `${stepProgress}%` }} />
                            </div>
                        </div>

                        <div className="mt-6 space-y-6">
                            <div>
                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Progress</h4>
                                <div className="mt-4 space-y-6">
                                    {STEPS.map((step) => {
                                        const isCompleted = currentStep > step.id;
                                        const isCurrent = currentStep === step.id;
                                        return (
                                            <div key={step.id} className="flex gap-4 group">
                                                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[12px] font-bold transition-colors bg-white ${isCompleted ? 'border-indigo-600 bg-indigo-600 text-white' : isCurrent ? 'border-indigo-600 text-indigo-600' : 'border-[#cbd5e1] text-slate-400 group-hover:border-slate-400'}`}>
                                                    {isCompleted ? (
                                                        <span className="material-symbols-outlined text-[16px]">check</span>
                                                    ) : step.id}
                                                </div>
                                                <div className="pt-1">
                                                    <p className={`text-[14px] font-bold ${isCurrent || isCompleted ? 'text-indigo-600' : 'text-slate-500'}`}>{step.title}</p>
                                                    <p className="text-[12px] text-slate-400 mt-1 leading-snug">{step.description}</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-5">
                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                                    {currentStep === 1 ? 'Get oriented' : currentStep === 2 ? 'Fill client details' : 'Tell the story'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {currentStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>We generate the case number and tracking ID for you.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Choose the right client type.</span></li>
                                        </>
                                    )}
                                    {currentStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Fill in complete client details.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Add work history and next of kin if applicable.</span></li>
                                        </>
                                    )}
                                    {currentStep === 3 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Capture the key events and timeline.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Review summary before final submission.</span></li>
                                        </>
                                    )}
                                </ul>
                                <p className="mt-4 text-[12px] text-slate-500">You can edit details later from the case view.</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 flex flex-col p-8">
                        <div className="flex-1">
                            <div className="mb-6 rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-50 via-white to-white p-6">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-indigo-600">Step {currentStep}</p>
                                        <h2 className="text-xl font-bold text-slate-800 mt-2">{STEPS[currentStep - 1].title}</h2>
                                        <p className="text-[13px] text-slate-500 mt-1">{STEPS[currentStep - 1].description}</p>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Progress</p>
                                        <p className="text-[14px] font-bold text-slate-800 mt-1">{stepProgress}% complete</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                {currentStep === 1 && (
                                    <div className="space-y-5">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Auto-generated identifiers</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Unique case number and tracking ID are generated automatically.</p>
                                            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
                                                <Field label="Case No.">
                                                    <Input value={caseId} readOnly />
                                                </Field>
                                                <Field label="Tracking ID">
                                                    <Input value={trackingId} readOnly />
                                                </Field>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Who is this case for?</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Pick the client category to tailor the next steps.</p>
                                            <div className="mt-4">
                                                <Field label="Client Type" required>
                                                    <select
                                                        value={data.client_type}
                                                        onChange={(e) => setData('client_type', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="OFW">Overseas Filipino Worker</option>
                                                        <option value="NEXT_OF_KIN">Next of Kin</option>
                                                    </select>
                                                </Field>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {currentStep === 2 && (
                                    <div className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <label className={`relative flex cursor-pointer rounded-lg border p-4 shadow-sm transition-all hover:border-indigo-500 ${clientSource === 'existing' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 bg-white'}`}>
                                                <input type="radio" name="client-source" className="sr-only" checked={clientSource === 'existing'} onChange={() => setClientSource('existing')} />
                                                <div className="flex w-full items-center justify-between">
                                                    <div>
                                                        <span className={`block text-sm font-bold ${clientSource === 'existing' ? 'text-indigo-600' : 'text-slate-900'}`}>Existing Client</span>
                                                        <span className="mt-1 flex items-center text-[13px] text-slate-500">Select from existing records</span>
                                                    </div>
                                                    {clientSource === 'existing' && <span className="material-symbols-outlined text-indigo-600">check</span>}
                                                </div>
                                            </label>
                                            <label className={`relative flex cursor-pointer rounded-lg border p-4 shadow-sm transition-all hover:border-indigo-500 ${clientSource === 'new' ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-slate-200 bg-white'}`}>
                                                <input type="radio" name="client-source" className="sr-only" checked={clientSource === 'new'} onChange={() => setClientSource('new')} />
                                                <div className="flex w-full items-center justify-between">
                                                    <div>
                                                        <span className={`block text-sm font-bold ${clientSource === 'new' ? 'text-indigo-600' : 'text-slate-900'}`}>New Client</span>
                                                        <span className="mt-1 flex items-center text-[13px] text-slate-500">Create a new client record</span>
                                                    </div>
                                                    {clientSource === 'new' && <span className="material-symbols-outlined text-indigo-600">check</span>}
                                                </div>
                                            </label>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Client Information">
                                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                    <Field label="First Name" required>
                                                        <Input value={data.client.first_name} onChange={(e) => handleClientChange('first_name', e.target.value)} />
                                                    </Field>
                                                    <Field label="Middle Name">
                                                        <Input value={data.client.middle_name} onChange={(e) => handleClientChange('middle_name', e.target.value)} />
                                                    </Field>
                                                    <Field label="Last Name" required>
                                                        <Input value={data.client.last_name} onChange={(e) => handleClientChange('last_name', e.target.value)} />
                                                    </Field>
                                                    <Field label="Suffix">
                                                        <Select value={data.client.suffix} onChange={(e) => handleClientChange('suffix', e.target.value)} options={SUFFIX_OPTIONS.filter(Boolean).map((s) => ({ label: s, value: s }))} placeholder="None" />
                                                    </Field>
                                                    <Field label="Date of Birth">
                                                        <Input type="date" value={data.client.date_of_birth} onChange={(e) => handleClientChange('date_of_birth', e.target.value)} />
                                                    </Field>
                                                    <Field label="Gender">
                                                        <Select value={clientGender} onChange={(e) => setClientGender(e.target.value)} options={[{ label: 'Male', value: 'Male' }, { label: 'Female', value: 'Female' }]} />
                                                    </Field>
                                                    <Field label="Email Address">
                                                        <Input type="email" value={clientEmail} onChange={(e) => setClientEmail(e.target.value)} />
                                                    </Field>
                                                    <Field label="Contact Number">
                                                        <Input value={clientContact} onChange={(e) => setClientContact(e.target.value)} placeholder="+63 XXX XXX XXXX" />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Address">
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="md:col-span-2">
                                                        <Field label="Street Address">
                                                            <Input value={data.address.line1} onChange={(e) => handleAddressChange('line1', e.target.value)} />
                                                        </Field>
                                                    </div>
                                                    <div className="md:col-span-2">
                                                        <Field label="Street Address Line 2">
                                                            <Input value={data.address.line2} onChange={(e) => handleAddressChange('line2', e.target.value)} />
                                                        </Field>
                                                    </div>
                                                    <Field label="City">
                                                        <Input value={data.address.city} onChange={(e) => handleAddressChange('city', e.target.value)} />
                                                    </Field>
                                                    <Field label="Province">
                                                        <Input value={data.address.province} onChange={(e) => handleAddressChange('province', e.target.value)} />
                                                    </Field>
                                                    <Field label="Postal Code">
                                                        <Input value={data.address.postal_code} onChange={(e) => handleAddressChange('postal_code', e.target.value)} />
                                                    </Field>
                                                    <Field label="Country">
                                                        <Input value={data.address.country} onChange={(e) => handleAddressChange('country', e.target.value)} />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Work History">
                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <Field label="Last Country of Employment">
                                                        <Input value={lastCountry} onChange={(e) => { setLastCountry(e.target.value); handleEmploymentChange('country', e.target.value); }} />
                                                    </Field>
                                                    <Field label="Last Job Position">
                                                        <Input value={lastJob} onChange={(e) => { setLastJob(e.target.value); handleEmploymentChange('position', e.target.value); }} />
                                                    </Field>
                                                    <Field label="Arrival Date in Philippines">
                                                        <Input type="date" value={arrivalDate} onChange={(e) => setArrivalDate(e.target.value)} />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Next of Kin Information">
                                                <div className="mb-4">
                                                    <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${hasNextOfKin ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="has-nok" className="sr-only" checked={hasNextOfKin} onChange={() => setHasNextOfKin(true)} /> Yes
                                                        </label>
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${!hasNextOfKin ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="has-nok" className="sr-only" checked={!hasNextOfKin} onChange={() => setHasNextOfKin(false)} /> No
                                                        </label>
                                                    </div>
                                                </div>
                                                {hasNextOfKin && (
                                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                        <Field label="First Name">
                                                            <Input value={nokFirstName} onChange={(e) => setNokFirstName(e.target.value)} />
                                                        </Field>
                                                        <Field label="Last Name">
                                                            <Input value={nokLastName} onChange={(e) => setNokLastName(e.target.value)} />
                                                        </Field>
                                                        <Field label="Relationship">
                                                            <Select value={nokRelationship} onChange={(e) => setNokRelationship(e.target.value)}
                                                                options={['Mother', 'Father', 'Spouse', 'Sibling', 'Other'].map((r) => ({ label: r, value: r }))}
                                                                placeholder="Select relationship" />
                                                        </Field>
                                                        <Field label="Contact Number">
                                                            <Input value={nokContact} onChange={(e) => setNokContact(e.target.value)} placeholder="+63 XXX XXX XXXX" />
                                                        </Field>
                                                    </div>
                                                )}
                                            </Subsection>
                                        </div>
                                    </div>
                                )}

                                {currentStep === 3 && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Narrative</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Use concise plain text to summarize the case background.</p>
                                            <div className="mt-4">
                                                <Field label="Narrative">
                                                    <textarea
                                                        rows={8}
                                                        value={data.summary}
                                                        onChange={(e) => setData('summary', e.target.value)}
                                                        placeholder="Describe the client situation and reason for opening the case..."
                                                        className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    />
                                                </Field>
                                            </div>
                                        </div>

                                        {clientSource === 'new' && (
                                            <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
                                                <h3 className="text-[12px] font-bold uppercase tracking-wider text-amber-800">Data Privacy Consent</h3>
                                                <p className="mt-2 text-[13px] leading-relaxed text-amber-900/90">
                                                    I confirm that the client has been informed about this system's data privacy terms and conditions, and
                                                    has given consent for their personal data to be collected, processed, and used for case management,
                                                    referral coordination, and service delivery.
                                                </p>
                                                <label className="mt-4 inline-flex items-start gap-3 text-[13px] font-semibold text-amber-900 cursor-pointer">
                                                    <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-700 focus:ring-amber-600" />
                                                    <span>I acknowledge and confirm client consent for data use in the system.</span>
                                                </label>
                                                {!consent && <p className="mt-3 text-[12px] font-medium text-amber-800">Required to create a case for a new client.</p>}
                                            </div>
                                        )}

                                        <div className="rounded-xl border border-slate-200 bg-[#fcfdff] p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Summary</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Review the core case details before you finalize creation.</p>
                                            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <Field label="Case No.">
                                                    <div className="h-10 w-full rounded-[3px] border border-[#cbd5e1] bg-slate-50 px-3 text-[13px] text-slate-700 flex items-center">{caseId}</div>
                                                </Field>
                                                <Field label="Tracking ID">
                                                    <div className="h-10 w-full rounded-[3px] border border-[#cbd5e1] bg-slate-50 px-3 text-[13px] text-slate-700 flex items-center">{trackingId}</div>
                                                </Field>
                                                <Field label="Client Type">
                                                    <div className="h-10 w-full rounded-[3px] border border-[#cbd5e1] bg-slate-50 px-3 text-[13px] text-slate-700 flex items-center">{data.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'}</div>
                                                </Field>
                                                <Field label="Client Name">
                                                    <div className="h-10 w-full rounded-[3px] border border-[#cbd5e1] bg-slate-50 px-3 text-[13px] text-slate-700 flex items-center">{[data.client.first_name, data.client.last_name].filter(Boolean).join(' ')}</div>
                                                </Field>
                                                <Field label="Next of Kin" className="md:col-span-2">
                                                    <div className="min-h-10 w-full rounded-[3px] border border-[#cbd5e1] bg-slate-50 px-3 py-2 text-[13px] text-slate-700">{nokSummary}</div>
                                                </Field>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
                            <button type="button" onClick={handleBack} disabled={currentStep === 1}
                                className="inline-flex items-center gap-2 rounded-md border border-[#cbd5e1] bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                            </button>
                            {currentStep < 3 ? (
                                <button type="button" onClick={handleNext} disabled={!canProceed()}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                </button>
                            ) : (
                                <button type="submit" disabled={processing || !canSubmit()}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? 'Creating...' : 'Create Case'}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </form>
        </AppLayout>
    );
}
