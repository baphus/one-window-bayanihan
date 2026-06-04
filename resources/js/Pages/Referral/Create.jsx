import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';

const STEPS = [
    { id: 1, title: 'Select Case', description: 'Choose the open case to refer' },
    { id: 2, title: 'Select Agency', description: 'Pick the receiving agency lane' },
    { id: 3, title: 'Services & Documents', description: 'Select services and attach requirements' },
];

// Server accepts up to 10MB per file (StoreReferralRequest: documents.* max:10240).
const MAX_FILE_BYTES = 10 * 1024 * 1024;

function buildServiceRequirementKey(serviceTitle, requirement) {
    return `${serviceTitle}::${requirement}`;
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function FieldLabel({ label, children, full, hint }) {
    return (
        <div className={full ? 'md:col-span-2' : ''}>
            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">{label}</label>
            {children}
            {hint && <p className="mt-1 text-[11px] text-slate-500">{hint}</p>}
        </div>
    );
}

function InfoRow({ label, value, subtext }) {
    return (
        <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{label}</p>
            <p className="mt-1 text-[13px] font-semibold text-slate-700">{value}</p>
            {subtext && <p className="text-[11px] text-slate-500 mt-0.5">{subtext}</p>}
        </div>
    );
}

function SectionCard({ title, description, children, tone = 'default' }) {
    const toneClass = tone === 'muted'
        ? 'border-slate-200 bg-[#fcfdff]'
        : 'border-slate-200 bg-white';
    return (
        <div className={`rounded-xl border ${toneClass} p-6 shadow-sm`}>
            {title && <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">{title}</h3>}
            {description && <p className="mt-2 text-[13px] text-slate-500">{description}</p>}
            <div className={title || description ? 'mt-4' : ''}>{children}</div>
        </div>
    );
}

export default function ReferralCreate({ case_id, agencies, cases: openCases }) {
    const { data, setData, post, processing, errors, transform } = useForm({
        case_id: case_id || '',
        agcy_id: '',
        services: [],
        notes: '',
    });

    const [createStep, setCreateStep] = useState(1);
    const [requirementUploads, setRequirementUploads] = useState({});
    const [fileErrors, setFileErrors] = useState({});
    const [notesValue, setNotesValue] = useState('');
    const initialFormRef = useRef({ case_id: data.case_id, agcy_id: '', services: [] });
    const hasDirty = useMemo(() => (
        data.case_id !== initialFormRef.current.case_id
        || data.agcy_id !== initialFormRef.current.agcy_id
        || JSON.stringify(data.services) !== JSON.stringify(initialFormRef.current.services)
        || notesValue !== ''
        || Object.keys(requirementUploads).length > 0
    ), [data.case_id, data.agcy_id, data.services, notesValue, requirementUploads]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);
    const selectedCase = openCases.find((item) => item.id === data.case_id);
    const selectedAgency = agencies.find((item) => item.id === data.agcy_id);

    const availableServices = useMemo(() => {
        if (!data.agcy_id || !selectedAgency) return [];
        const agency = agencies.find((a) => a.id === data.agcy_id);
        return agency?.services?.map((s) => s.name) || [];
    }, [data.agcy_id, agencies, selectedAgency]);

    const selectedServiceDetails = useMemo(() => {
        if (!data.agcy_id || !data.services.length) return [];
        const agency = agencies.find((a) => a.id === data.agcy_id);
        if (!agency?.services) return [];
        const selectedSet = new Set(data.services);
        return agency.services.filter((s) => selectedSet.has(s.name));
    }, [data.agcy_id, data.services, agencies]);

    const selectedServiceRequirements = useMemo(() => {
        return selectedServiceDetails.flatMap((service) =>
            (service.requirements || []).map((req) => ({
                key: buildServiceRequirementKey(service.name, req.name),
                serviceTitle: service.name,
                requirement: req.name,
            }))
        );
    }, [selectedServiceDetails]);

    const missingRequirementKeys = useMemo(() => {
        return selectedServiceRequirements.filter((item) => !requirementUploads[item.key]).map((item) => item.key);
    }, [selectedServiceRequirements, requirementUploads]);

    const hasMissingRequirementUploads = missingRequirementKeys.length > 0;
    const hasFileErrors = Object.keys(fileErrors).length > 0;

    const totalUploadBytes = useMemo(() => (
        Object.values(requirementUploads).reduce((sum, file) => sum + (file?.size || 0), 0)
    ), [requirementUploads]);

    useEffect(() => {
        if (!data.case_id || !data.agcy_id) return;
        const agency = agencies.find((a) => a.id === data.agcy_id);
        if (!agency?.services?.length) return;

        setData('services', (current) => {
            const valid = current.filter((s) => agency.services.some((as) => as.name === s));
            if (valid.length) return valid;
            return [agency.services[0].name];
        });
    }, [data.agcy_id]);

    useEffect(() => {
        const activeKeys = new Set(selectedServiceRequirements.map((item) => item.key));
        setRequirementUploads((current) => {
            const next = Object.fromEntries(
                Object.entries(current).filter(([key]) => activeKeys.has(key))
            );
            return Object.keys(next).length === Object.keys(current).length ? current : next;
        });
        setFileErrors((current) => {
            const next = Object.fromEntries(
                Object.entries(current).filter(([key]) => activeKeys.has(key))
            );
            return Object.keys(next).length === Object.keys(current).length ? current : next;
        });
    }, [selectedServiceRequirements]);

    const isStepOneValid = Boolean(selectedCase);
    const isStepTwoValid = Boolean(data.agcy_id);
    const isStepThreeValid = Boolean(
        data.case_id && data.agcy_id && data.services.length > 0 && !hasMissingRequirementUploads && !hasFileErrors
    );

    const stepProgress = Math.round((createStep / STEPS.length) * 100);
    const currentStepMeta = STEPS[createStep - 1];

    function goToNextStep() {
        if (createStep === 1 && isStepOneValid) { setCreateStep(2); return; }
        if (createStep === 2 && isStepTwoValid) { setCreateStep(3); }
    }

    function goToPreviousStep() {
        if (createStep === 2) { setCreateStep(1); return; }
        if (createStep === 3) { setCreateStep(2); }
    }

    function goToStep(stepId) {
        // Allow jumping back to an already-visited step only.
        if (stepId < createStep) setCreateStep(stepId);
    }

    function toggleServiceSelection(service) {
        setData('services', (current) =>
            current.includes(service)
                ? current.filter((item) => item !== service)
                : [...current, service]
        );
    }

    function handleFileChange(requirementKey, file) {
        if (!file) {
            setRequirementUploads((current) => {
                const next = { ...current };
                delete next[requirementKey];
                return next;
            });
            setFileErrors((current) => {
                const next = { ...current };
                delete next[requirementKey];
                return next;
            });
            return;
        }

        if (file.size > MAX_FILE_BYTES) {
            // Reject before submit so the user never hits the opaque
            // "Content Too Large" (PostTooLargeException) server error.
            setFileErrors((current) => ({
                ...current,
                [requirementKey]: `"${file.name}" is ${formatBytes(file.size)}. Maximum allowed is 10 MB.`,
            }));
            setRequirementUploads((current) => {
                const next = { ...current };
                delete next[requirementKey];
                return next;
            });
            return;
        }

        setFileErrors((current) => {
            const next = { ...current };
            delete next[requirementKey];
            return next;
        });
        setRequirementUploads((current) => ({ ...current, [requirementKey]: file }));
    }

    function parseRequiredDocs(service) {
        if (!service?.requirements) return [];
        return service.requirements.map((r) => r.name);
    }

    function submitReferral(e) {
        e.preventDefault();
        if (!isStepThreeValid) return;

        // Build the final payload at submit time. Using transform avoids the
        // stale-state bug where setData() (async) + post() in the same tick
        // would send the request before documents/notes were applied.
        transform((current) => {
            const documents = {};
            Object.entries(requirementUploads).forEach(([key, file]) => {
                if (file) documents[key] = file;
            });
            return { ...current, notes: notesValue, documents };
        });

        bypassNext();

        post(route('referrals.store'), {
            forceFormData: true,
            onSuccess: () => { },
            onError: () => { },
        });
    }

    return (
        <AppLayout title="Create Referral">
            <Head title="Create Referral" />

            <div className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-4">
                <Link href={route('referrals.index')} className="hover:text-indigo-600 transition">Referrals</Link>
                <span className="mx-2">&gt;</span>
                <span>New Referral</span>
            </div>

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">New Referral</h1>
                        <p className="text-sm text-slate-500 mt-1">A guided flow to dispatch a referral to the right agency lane.</p>
                    </div>
                    <Link href={route('referrals.index')} className="text-sm text-indigo-600 hover:text-indigo-900">&larr; Back to Referrals</Link>
                </div>
            </div>

            <form onSubmit={submitReferral}>
                <section className="mx-auto flex max-w-6xl overflow-visible rounded-xl border border-[#cbd5e1] bg-white shadow-sm">
                    {/* Left Step Guide sidebar */}
                    <div className="w-1/3 min-w-[280px] max-w-[320px] shrink-0 border-r border-[#cbd5e1] bg-slate-50/60 p-8">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Step Guide</h3>
                            <p className="mt-2 text-[14px] font-bold text-slate-800">Step {createStep} of {STEPS.length}</p>
                            <p className="mt-1 text-[12px] text-slate-500">Estimated time: 2-3 minutes</p>
                            <div className="mt-4 h-2 w-full rounded-full bg-slate-100">
                                <div className="h-2 rounded-full bg-indigo-600 transition-all" style={{ width: `${stepProgress}%` }} />
                            </div>
                        </div>

                        <div className="mt-6 space-y-6">
                            <div>
                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Progress</h4>
                                <div className="mt-4 space-y-6">
                                    {STEPS.map((step) => {
                                        const isCompleted = createStep > step.id;
                                        const isCurrent = createStep === step.id;
                                        const isClickable = step.id < createStep;
                                        return (
                                            <button
                                                type="button"
                                                key={step.id}
                                                onClick={() => goToStep(step.id)}
                                                disabled={!isClickable}
                                                className={`flex w-full gap-4 text-left group ${isClickable ? 'cursor-pointer' : 'cursor-default'}`}
                                            >
                                                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[12px] font-bold leading-none transition-colors ${isCompleted ? 'border-indigo-600 bg-indigo-600 text-white' : isCurrent ? 'border-indigo-600 bg-white text-indigo-600' : 'border-[#cbd5e1] bg-white text-slate-400 group-hover:border-slate-400'}`}>
                                                    {isCompleted ? (
                                                        <span className="material-symbols-outlined text-[16px] leading-none text-white" style={{ verticalAlign: 'middle', fontVariationSettings: "'FILL' 1" }}>check</span>
                                                    ) : step.id}
                                                </div>
                                                <div className="pt-1">
                                                    <p className={`text-[14px] font-bold ${isCurrent || isCompleted ? 'text-indigo-600' : 'text-slate-500'}`}>{step.title}</p>
                                                    <p className="text-[12px] text-slate-400 mt-1 leading-snug">{step.description}</p>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-5">
                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                                    {createStep === 1 ? 'Pick the case' : createStep === 2 ? 'Route the request' : 'Prepare documents'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {createStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Only open cases can be referred.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Confirm the client details before continuing.</span></li>
                                        </>
                                    )}
                                    {createStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>The agency lane controls who can act on this referral.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>You can dispatch separate referrals to multiple agencies.</span></li>
                                        </>
                                    )}
                                    {createStep === 3 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Attach one file per required document.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Each file must be 10 MB or smaller.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Add remarks for the receiving agency if needed.</span></li>
                                        </>
                                    )}
                                </ul>
                                <p className="mt-4 text-[12px] text-slate-500">You can review everything before submitting.</p>
                            </div>
                        </div>
                    </div>

                    {/* Right content panel */}
                    <div className="flex-1 flex flex-col p-8">
                        <div className="flex-1">
                            <div className="mb-6 rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-50 via-white to-white p-6">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-indigo-600">Step {createStep}</p>
                                        <h2 className="text-xl font-bold text-slate-800 mt-2">{currentStepMeta.title}</h2>
                                        <p className="text-[13px] text-slate-500 mt-1">{currentStepMeta.description}</p>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Progress</p>
                                        <p className="text-[14px] font-bold text-slate-800 mt-1">{stepProgress}% complete</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                {/* STEP 1 — Select Case */}
                                {createStep === 1 && (
                                    <SectionCard title="Case Selection" description="Choose the open case you want to refer to an agency.">
                                        <FieldLabel label="Case" full hint={openCases.length === 0 ? 'There are no open cases available to refer.' : 'Only cases with an OPEN status are listed.'}>
                                            <select
                                                value={data.case_id}
                                                disabled={openCases.length === 0}
                                                onChange={(e) => {
                                                    const nextCase = openCases.find((c) => c.id === e.target.value);
                                                    setData('case_id', e.target.value);
                                                    if (nextCase) {
                                                        setData('agcy_id', '');
                                                        setData('services', []);
                                                    }
                                                }}
                                                className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-50"
                                            >
                                                <option value="">Select a case...</option>
                                                {openCases.map((item) => (
                                                    <option key={item.id} value={item.id}>
                                                        {item.case_number} - {item.client?.first_name} {item.client?.last_name}
                                                    </option>
                                                ))}
                                            </select>
                                        </FieldLabel>

                                        {selectedCase && (
                                            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-4">
                                                <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Selected Case Details</p>
                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <InfoRow label="Case Number" value={selectedCase.case_number} />
                                                    <InfoRow label="Client Name" value={`${selectedCase.client?.first_name || ''} ${selectedCase.client?.last_name || ''}`.trim() || 'N/A'} />
                                                    <InfoRow label="Client Type" value={selectedCase.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'} />
                                                    <InfoRow label="Status" value={selectedCase.status} />
                                                    <InfoRow label="Date Created" value={formatDisplayDate(selectedCase.created_at)} subtext={formatDisplayTime(selectedCase.created_at)} />
                                                    {selectedCase.summary && (
                                                        <div className="md:col-span-2">
                                                            <InfoRow label="Case Narrative" value={selectedCase.summary} />
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </SectionCard>
                                )}

                                {/* STEP 2 — Select Agency */}
                                {createStep === 2 && (
                                    <SectionCard title="Receiving Agency" description="Select the agency lane that will process this referral.">
                                        {selectedCase && (
                                            <div className="mb-4 rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-[13px] text-indigo-700">
                                                Referring case <span className="font-bold">{selectedCase.case_number}</span> for <span className="font-bold">{selectedCase.client?.first_name} {selectedCase.client?.last_name}</span>.
                                            </div>
                                        )}
                                        <FieldLabel label="Agency" full hint="The selected agency determines which services are available next.">
                                            <select
                                                value={data.agcy_id}
                                                onChange={(e) => {
                                                    const nextAgencyId = e.target.value;
                                                    setData('agcy_id', nextAgencyId);
                                                    setData('services', (current) => {
                                                        const agency = agencies.find((a) => a.id === nextAgencyId);
                                                        const nextServices = agency?.services?.map((s) => s.name) || [];
                                                        const valid = current.filter((s) => nextServices.includes(s));
                                                        if (valid.length) return valid;
                                                        return nextServices.length ? [nextServices[0]] : [];
                                                    });
                                                }}
                                                className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            >
                                                <option value="">Select an agency...</option>
                                                {agencies.map((agency) => (
                                                    <option key={agency.id} value={agency.id}>{agency.name}</option>
                                                ))}
                                            </select>
                                        </FieldLabel>

                                        {selectedAgency && (
                                            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-4">
                                                <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Agency Overview</p>
                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <InfoRow label="Agency" value={selectedAgency.name} />
                                                    <InfoRow label="Available Services" value={`${selectedAgency.services?.length || 0} service(s)`} />
                                                </div>
                                            </div>
                                        )}
                                    </SectionCard>
                                )}

                                {/* STEP 3 — Services & Documents */}
                                {createStep === 3 && (
                                    <>
                                        <SectionCard title="Services" description="Select one or more services to request from this agency.">
                                            {availableServices.length ? (
                                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                    {availableServices.map((service) => {
                                                        const checked = data.services.includes(service);
                                                        return (
                                                            <label
                                                                key={service}
                                                                className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-[13px] transition-colors ${checked ? 'border-indigo-300 bg-indigo-50 text-indigo-800' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'}`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={checked}
                                                                    onChange={() => toggleServiceSelection(service)}
                                                                    className="h-4 w-4 rounded border-[#cbd5e1] text-indigo-600 focus:ring-indigo-500"
                                                                />
                                                                <span className="font-semibold">{service}</span>
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <p className="text-[13px] text-slate-500">No available services for this agency.</p>
                                            )}
                                        </SectionCard>

                                        <SectionCard title="Service Requirements" description="Attach the required supporting document for each selected service.">
                                            {selectedServiceDetails.length ? (
                                                <div className="space-y-4">
                                                    {selectedServiceDetails.map((service) => {
                                                        const docs = parseRequiredDocs(service);
                                                        return (
                                                            <div key={service.name} className="rounded-lg border border-slate-200 bg-white px-4 py-4">
                                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                                    <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{service.name}</p>
                                                                    <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700">
                                                                        {service.processing_days || 'N/A'} business days
                                                                    </span>
                                                                </div>

                                                                {docs.length ? (
                                                                    <div className="mt-3 space-y-3">
                                                                        {docs.map((requirement) => {
                                                                            const requirementKey = buildServiceRequirementKey(service.name, requirement);
                                                                            const uploadedFile = requirementUploads[requirementKey];
                                                                            const hasFile = Boolean(uploadedFile);
                                                                            const fileError = fileErrors[requirementKey];
                                                                            return (
                                                                                <div key={requirementKey}
                                                                                    className={`rounded-lg border px-3 py-3 ${fileError ? 'border-rose-300 bg-rose-50/60' : hasFile ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50/60'}`}
                                                                                >
                                                                                    <div className="flex items-center justify-between gap-2">
                                                                                        <p className="text-[12px] font-semibold text-slate-700">{requirement}</p>
                                                                                        {hasFile && (
                                                                                            <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700">
                                                                                                <span className="material-symbols-outlined text-[14px] leading-none" style={{ fontVariationSettings: "'FILL' 1" }}>check_circle</span>
                                                                                                Attached
                                                                                            </span>
                                                                                        )}
                                                                                    </div>
                                                                                    <input
                                                                                        type="file"
                                                                                        onChange={(e) => handleFileChange(requirementKey, e.target.files?.[0] || null)}
                                                                                        className="mt-2 block w-full rounded-[3px] border border-[#cbd5e1] bg-white px-3 py-2 text-[12px] text-slate-700 file:mr-3 file:rounded-[3px] file:border-0 file:bg-indigo-50 file:px-3 file:py-1 file:text-[11px] file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                                                                    />
                                                                                    {fileError ? (
                                                                                        <p className="mt-1.5 text-[11px] font-semibold text-rose-700">{fileError}</p>
                                                                                    ) : hasFile ? (
                                                                                        <p className="mt-1.5 text-[11px] text-slate-500">{uploadedFile.name} &middot; {formatBytes(uploadedFile.size)}</p>
                                                                                    ) : (
                                                                                        <p className="mt-1.5 text-[11px] text-slate-500">Upload required (PDF, DOC, or image up to 10 MB).</p>
                                                                                    )}
                                                                                </div>
                                                                            );
                                                                        })}
                                                                    </div>
                                                                ) : (
                                                                    <p className="mt-2 text-[12px] text-slate-500">No listed requirements for this service.</p>
                                                                )}
                                                            </div>
                                                        );
                                                    })}

                                                    {(hasMissingRequirementUploads || totalUploadBytes > 0) && (
                                                        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-3 text-[12px]">
                                                            <span className={hasMissingRequirementUploads ? 'font-semibold text-rose-700' : 'font-semibold text-emerald-700'}>
                                                                {hasMissingRequirementUploads
                                                                    ? `${missingRequirementKeys.length} document(s) still need an attachment.`
                                                                    : 'All required documents attached.'}
                                                            </span>
                                                            <span className="text-slate-500">Total upload size: {formatBytes(totalUploadBytes)}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            ) : (
                                                <p className="text-[13px] text-slate-500">Select one or more services above to see their requirements.</p>
                                            )}
                                        </SectionCard>

                                        <SectionCard title="Remarks" description="Optional context for the receiving agency." tone="muted">
                                            <textarea
                                                rows={4}
                                                value={notesValue}
                                                onChange={(e) => setNotesValue(e.target.value)}
                                                placeholder="Add any relevant notes or special instructions..."
                                                className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            />
                                        </SectionCard>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Footer navigation */}
                        <div className="mt-8 border-t border-slate-200 pt-6">
                            {(errors.services || errors.case_id || errors.agcy_id) && (
                                <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-[12px] text-rose-700">
                                    {errors.case_id && <p>{errors.case_id}</p>}
                                    {errors.agcy_id && <p>{errors.agcy_id}</p>}
                                    {errors.services && <p>{errors.services}</p>}
                                </div>
                            )}
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={route('referrals.index')}
                                        className="inline-flex items-center gap-2 rounded-md border border-[#cbd5e1] bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Cancel
                                    </Link>
                                    {createStep > 1 && (
                                        <button
                                            type="button"
                                            onClick={goToPreviousStep}
                                            className="inline-flex items-center gap-2 rounded-md border border-[#cbd5e1] bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                                        </button>
                                    )}
                                </div>

                                {createStep < 3 ? (
                                    <button
                                        type="button"
                                        onClick={goToNextStep}
                                        disabled={(createStep === 1 && !isStepOneValid) || (createStep === 2 && !isStepTwoValid)}
                                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                    </button>
                                ) : (
                                    <button
                                        type="submit"
                                        disabled={processing || !isStepThreeValid}
                                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">send</span>
                                        {processing ? 'Submitting...' : 'Submit Referral'}
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </form>
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
