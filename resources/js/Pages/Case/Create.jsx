import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import AddressDropdowns from '@/Components/AddressDropdowns';
import CountrySelect from '@/Components/CountrySelect';

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
    return `OWBAP-${token}`;
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
    const { client, existingClients = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        client_type: 'OFW',
        vulnerability_indicator: '',
        summary: '',
        client: {
            first_name: '',
            last_name: '',
            middle_name: '',
            suffix: '',
            date_of_birth: '',
            sex: '',
            email: '',
            contact_number: '',
        },
        address: {
            region: '',
            province: '',
            city_municipality: '',
            barangay: '',
            street: '',
        },
        employment: {
            employer_name: '',
            position: '',
            country: '',
            start_date: '',
            end_date: '',
            last_country: '',
            last_position: '',
            date_of_arrival: '',
        },
        next_of_kin: {
            first_name: '',
            middle_initial: '',
            last_name: '',
            is_primary: false,
            relationship: '',
            phone_number: '',
            email: '',
            full_address: '',
        },
        consent: false,
        selected_client_id: '',
        is_draft: false,
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

    const initialFormRef = useRef({
        formData: {
            client_type: 'OFW',
            vulnerability_indicator: '',
            summary: '',
            client: { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: '', email: '', contact_number: '' },
            address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
            employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '' },
            next_of_kin: { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '' },
            consent: false,
            is_draft: false,
        },
        useState: {
            clientSource: 'new',
            nokFirstName: '', nokLastName: '', nokContact: '', nokRelationship: '',
            clientGender: 'Male', clientEmail: '', clientContact: '',
            lastCountry: '', lastJob: '', arrivalDate: '',
            hasNextOfKin: true, consent: false,
        },
    });

    function formDataEqual(a, b) {
        return a.client_type === b.client_type
            && a.vulnerability_indicator === b.vulnerability_indicator
            && a.summary === b.summary
            && a.client.first_name === b.client.first_name
            && a.client.last_name === b.client.last_name
            && a.client.middle_name === b.client.middle_name
            && a.client.suffix === b.client.suffix
            && a.client.date_of_birth === b.client.date_of_birth
            && a.client.sex === b.client.sex
            && a.client.email === b.client.email
            && a.client.contact_number === b.client.contact_number
            && a.address.region === b.address.region
            && a.address.province === b.address.province
            && a.address.city_municipality === b.address.city_municipality
            && a.address.barangay === b.address.barangay
            && a.address.street === b.address.street
            && a.employment.employer_name === b.employment.employer_name
            && a.employment.position === b.employment.position
            && a.employment.country === b.employment.country
            && a.employment.start_date === b.employment.start_date
            && a.employment.end_date === b.employment.end_date
            && a.employment.last_country === b.employment.last_country
            && a.employment.last_position === b.employment.last_position
            && a.employment.date_of_arrival === b.employment.date_of_arrival
            && a.next_of_kin.first_name === b.next_of_kin.first_name
            && a.next_of_kin.middle_initial === b.next_of_kin.middle_initial
            && a.next_of_kin.last_name === b.next_of_kin.last_name
            && a.next_of_kin.is_primary === b.next_of_kin.is_primary
            && a.next_of_kin.relationship === b.next_of_kin.relationship
            && a.next_of_kin.phone_number === b.next_of_kin.phone_number
            && a.next_of_kin.email === b.next_of_kin.email
            && a.next_of_kin.full_address === b.next_of_kin.full_address
            && a.consent === b.consent
            && a.is_draft === b.is_draft;
    }

    const hasDirty = useMemo(() => {
        const i = initialFormRef.current;
        return !formDataEqual(data, i.formData)
            || clientSource !== i.useState.clientSource
            || nokFirstName !== i.useState.nokFirstName
            || nokLastName !== i.useState.nokLastName
            || nokContact !== i.useState.nokContact
            || nokRelationship !== i.useState.nokRelationship
            || clientGender !== i.useState.clientGender
            || clientEmail !== i.useState.clientEmail
            || clientContact !== i.useState.clientContact
            || lastCountry !== i.useState.lastCountry
            || lastJob !== i.useState.lastJob
            || arrivalDate !== i.useState.arrivalDate
            || hasNextOfKin !== i.useState.hasNextOfKin
            || consent !== i.useState.consent;
    }, [data, clientSource, nokFirstName, nokLastName, nokContact, nokRelationship, clientGender, clientEmail, clientContact, lastCountry, lastJob, arrivalDate, hasNextOfKin, consent]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);

    useEffect(() => {
        if (client) {
            setClientSource('existing');
            setData('selected_client_id', client.id);
            setClientGender(client.sex || 'Male');
            setClientEmail(client.email || '');
            setClientContact(client.contact_number || '');

            setData('client', {
                ...data.client,
                first_name: client.first_name || '',
                last_name: client.last_name || '',
                middle_name: client.middle_name || '',
                suffix: client.suffix || '',
                date_of_birth: client.date_of_birth || '',
                sex: client.sex || '',
                email: client.email || '',
                contact_number: client.contact_number || '',
            });

            if (client.addresses?.[0]) {
                setData('address', {
                    ...data.address,
                    region: client.addresses[0].region || '',
                    province: client.addresses[0].province || '',
                    city_municipality: client.addresses[0].city_municipality || '',
                    barangay: client.addresses[0].barangay || '',
                    street: client.addresses[0].street || '',
                });
            }

            if (client.employments?.[0]) {
                setData('employment', {
                    ...data.employment,
                    employer_name: client.employments[0].employer_name || '',
                    position: client.employments[0].position || '',
                    country: client.employments[0].country || '',
                    last_country: client.employments[0].last_country || '',
                    last_position: client.employments[0].last_position || '',
                    date_of_arrival: client.employments[0].date_of_arrival || '',
                });
                setLastCountry(client.employments[0].last_country || client.employments[0].country || '');
                setLastJob(client.employments[0].last_position || client.employments[0].position || '');
                setArrivalDate(client.employments[0].date_of_arrival || '');
            }

            if (client.nextOfKin?.[0]) {
                setData('next_of_kin', {
                    ...data.next_of_kin,
                    first_name: client.nextOfKin[0].first_name || '',
                    middle_initial: client.nextOfKin[0].middle_initial || '',
                    last_name: client.nextOfKin[0].last_name || '',
                    relationship: client.nextOfKin[0].relationship || '',
                    phone_number: client.nextOfKin[0].phone_number || '',
                    email: client.nextOfKin[0].email || '',
                    full_address: client.nextOfKin[0].full_address || '',
                });
                setNokFirstName(client.nextOfKin[0].first_name || '');
                setNokLastName(client.nextOfKin[0].last_name || '');
                setNokContact(client.nextOfKin[0].phone_number || '');
                setNokRelationship(client.nextOfKin[0].relationship || '');
            }

            // Update initial ref so dirty tracking starts from pre-filled state
            initialFormRef.current = {
                formData: {
                    client_type: 'OFW',
                    vulnerability_indicator: '',
                    summary: '',
                    client: {
                        first_name: client.first_name || '',
                        last_name: client.last_name || '',
                        middle_name: client.middle_name || '',
                        suffix: client.suffix || '',
                        date_of_birth: client.date_of_birth || '',
                        sex: client.sex || '',
                        email: client.email || '',
                        contact_number: client.contact_number || '',
                    },
                    address: {
                        region: client.addresses?.[0]?.region || '',
                        province: client.addresses?.[0]?.province || '',
                        city_municipality: client.addresses?.[0]?.city_municipality || '',
                        barangay: client.addresses?.[0]?.barangay || '',
                        street: client.addresses?.[0]?.street || '',
                    },
                    employment: {
                        employer_name: client.employments?.[0]?.employer_name || '',
                        position: client.employments?.[0]?.position || '',
                        country: client.employments?.[0]?.country || '',
                        start_date: client.employments?.[0]?.start_date || '',
                        end_date: client.employments?.[0]?.end_date || '',
                        last_country: client.employments?.[0]?.last_country || '',
                        last_position: client.employments?.[0]?.last_position || '',
                        date_of_arrival: client.employments?.[0]?.date_of_arrival || '',
                    },
                    next_of_kin: {
                        first_name: client.nextOfKin?.[0]?.first_name || '',
                        middle_initial: client.nextOfKin?.[0]?.middle_initial || '',
                        last_name: client.nextOfKin?.[0]?.last_name || '',
                        is_primary: false,
                        relationship: client.nextOfKin?.[0]?.relationship || '',
                        phone_number: client.nextOfKin?.[0]?.phone_number || '',
                        email: client.nextOfKin?.[0]?.email || '',
                        full_address: client.nextOfKin?.[0]?.full_address || '',
                    },
                    consent: false,
                    is_draft: false,
                },
                useState: {
                    clientSource: 'existing',
                    nokFirstName: client.nextOfKin?.[0]?.first_name || '',
                    nokLastName: client.nextOfKin?.[0]?.last_name || '',
                    nokContact: client.nextOfKin?.[0]?.phone_number || '',
                    nokRelationship: client.nextOfKin?.[0]?.relationship || '',
                    clientGender: client.sex || 'Male',
                    clientEmail: client.email || '',
                    clientContact: client.contact_number || '',
                    lastCountry: client.employments?.[0]?.last_country || client.employments?.[0]?.country || '',
                    lastJob: client.employments?.[0]?.last_position || client.employments?.[0]?.position || '',
                    arrivalDate: client.employments?.[0]?.date_of_arrival || '',
                    hasNextOfKin: true,
                    consent: false,
                },
            };
        }
    }, []);

    const stepProgress = Math.round((currentStep / STEPS.length) * 100);

    function handleClientChange(field, value) {
        setData('client', { ...data.client, [field]: value });
    }

    function handleClientDropdownChange(e) {
        const clientId = e.target.value;
        setData('selected_client_id', clientId);

        if (!clientId) {
            setClientSource('new');
            setData('client', { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: '', email: '', contact_number: '' });
            setData('address', { region: '', province: '', city_municipality: '', barangay: '', street: '' });
            setData('employment', { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '' });
            setData('next_of_kin', { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '' });
            setClientGender('Male');
            setClientEmail('');
            setClientContact('');
            setLastCountry('');
            setLastJob('');
            setArrivalDate('');
            setNokFirstName('');
            setNokLastName('');
            setNokContact('');
            setNokRelationship('');
            setHasNextOfKin(true);
            return;
        }

        setClientSource('existing');
        const c = existingClients.find((x) => x.id === clientId);
        if (!c) return;

        setData('client', {
            ...data.client,
            first_name: c.first_name || '',
            last_name: c.last_name || '',
            middle_name: c.middle_name || '',
            suffix: c.suffix || '',
            date_of_birth: c.date_of_birth || '',
            sex: c.sex || '',
            email: c.email || '',
            contact_number: c.contact_number || '',
        });

        setClientGender(c.sex || 'Male');
        setClientEmail(c.email || '');
        setClientContact(c.contact_number || '');

        if (c.addresses?.[0]) {
            setData('address', {
                ...data.address,
                region: c.addresses[0].region || '',
                province: c.addresses[0].province || '',
                city_municipality: c.addresses[0].city_municipality || '',
                barangay: c.addresses[0].barangay || '',
                street: c.addresses[0].street || '',
            });
        }

        if (c.employments?.[0]) {
            setData('employment', {
                ...data.employment,
                employer_name: c.employments[0].employer_name || '',
                position: c.employments[0].position || '',
                country: c.employments[0].country || '',
                last_country: c.employments[0].last_country || '',
                last_position: c.employments[0].last_position || '',
                date_of_arrival: c.employments[0].date_of_arrival || '',
            });
            setLastCountry(c.employments[0].last_country || c.employments[0].country || '');
            setLastJob(c.employments[0].last_position || c.employments[0].position || '');
            setArrivalDate(c.employments[0].date_of_arrival || '');
        }

        if (c.nextOfKin?.[0]) {
            setData('next_of_kin', {
                ...data.next_of_kin,
                first_name: c.nextOfKin[0].first_name || '',
                middle_initial: c.nextOfKin[0].middle_initial || '',
                last_name: c.nextOfKin[0].last_name || '',
                relationship: c.nextOfKin[0].relationship || '',
                phone_number: c.nextOfKin[0].phone_number || '',
                email: c.nextOfKin[0].email || '',
                full_address: c.nextOfKin[0].full_address || '',
            });
            setNokFirstName(c.nextOfKin[0].first_name || '');
            setNokLastName(c.nextOfKin[0].last_name || '');
            setNokContact(c.nextOfKin[0].phone_number || '');
            setNokRelationship(c.nextOfKin[0].relationship || '');
        }
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

    function syncFormData() {
        setData('client', {
            ...data.client,
            sex: clientGender,
            email: clientEmail,
            contact_number: clientContact,
        });
        setData('consent', consent);
        setData('employment', {
            ...data.employment,
            country: lastCountry || data.employment.country,
            position: lastJob || data.employment.position,
            last_country: lastCountry,
            last_position: lastJob,
            date_of_arrival: arrivalDate,
        });
        if (hasNextOfKin) {
            setData('next_of_kin', {
                first_name: nokFirstName,
                last_name: nokLastName,
                relationship: nokRelationship,
                phone_number: nokContact,
            });
        }
    }

    function handleSaveDraft(e) {
        e.preventDefault();
        syncFormData();
        setData('is_draft', true);
        setData('consent', consent);
        bypassNext();
        post(route('cases.store'), {
            onSuccess: () => { },
            preserveState: false,
        });
    }

    function handleSubmit(e) {
        e.preventDefault();
        syncFormData();
        bypassNext();
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

            {client && (
                <div className="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-700">
                    <strong>Pre-filled</strong> from existing client record: {[client.first_name, client.last_name].filter(Boolean).join(' ')}
                </div>
            )}
            {data.selected_client_id && existingClients.find((c) => c.id === data.selected_client_id) && (
                <div className="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-700">
                    <strong>Pre-filled</strong> from existing client record: {existingClients.find((c) => c.id === data.selected_client_id).full_name}
                </div>
            )}

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
                                            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-5">
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
                                                <Field label="Vulnerability Indicator">
                                                    <select
                                                        value={data.vulnerability_indicator}
                                                        onChange={(e) => setData('vulnerability_indicator', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="">Select vulnerability...</option>
                                                        <option value="PWD">PWD</option>
                                                        <option value="Senior Citizen">Senior Citizen</option>
                                                        <option value="Solo Parent">Solo Parent</option>
                                                        <option value="Indigenous Person">Indigenous Person</option>
                                                        <option value="None">None</option>
                                                    </select>
                                                </Field>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {currentStep === 2 && (
                                    <div className="space-y-6">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Existing Client Record">
                                                <p className="mb-3 text-[13px] text-slate-500">
                                                    Select an existing client to pre-fill their information, or leave empty to create a new client record.
                                                </p>
                                                <Field label="Select Existing Client">
                                                    <select
                                                        value={data.selected_client_id}
                                                        onChange={handleClientDropdownChange}
                                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="">— Create new client —</option>
                                                        {existingClients.map((c) => (
                                                            <option key={c.id} value={c.id}>
                                                                {c.full_name}{c.has_case ? ' (has existing case)' : ''}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </Field>
                                            </Subsection>
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
                                                <AddressDropdowns
                                                    values={data.address}
                                                    onChange={handleAddressChange}
                                                />
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Work History">
                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <Field label="Last Country of Employment">
                                                        <CountrySelect value={lastCountry} onChange={(v) => { setLastCountry(v); handleEmploymentChange('last_country', v); }} placeholder="Select country..." />
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
                                                        <Field label="Middle Initial">
                                                            <Input value={data.next_of_kin.middle_initial} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, middle_initial: e.target.value })} maxLength={10} />
                                                        </Field>
                                                        <Field label="Last Name">
                                                            <Input value={nokLastName} onChange={(e) => setNokLastName(e.target.value)} />
                                                        </Field>
                                                        <Field label="Relationship">
                                                            <Select value={nokRelationship} onChange={(e) => setNokRelationship(e.target.value)}
                                                                options={['Mother', 'Father', 'Spouse', 'Sibling', 'Other'].map((r) => ({ label: r, value: r }))}
                                                                placeholder="Select relationship" />
                                                        </Field>
                                                        <Field label="Phone Number">
                                                            <Input value={nokContact} onChange={(e) => setNokContact(e.target.value)} placeholder="+63 XXX XXX XXXX" />
                                                        </Field>
                                                        <Field label="Email">
                                                            <Input type="email" value={data.next_of_kin.email} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, email: e.target.value })} />
                                                        </Field>
                                                        <div className="md:col-span-2">
                                                            <Field label="Full Address">
                                                                <Input value={data.next_of_kin.full_address} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, full_address: e.target.value })} />
                                                            </Field>
                                                        </div>
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
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={handleBack} disabled={currentStep === 1}
                                    className="inline-flex items-center gap-2 rounded-md border border-[#cbd5e1] bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                                </button>
                                <button type="button" onClick={handleSaveDraft} disabled={processing}
                                    className="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-5 py-2.5 text-[13px] font-bold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">save</span>
                                    {processing ? 'Saving...' : 'Save as Draft'}
                                </button>
                            </div>
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
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
