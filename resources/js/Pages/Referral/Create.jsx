import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import InputError from '@/Components/InputError';
import FileUpload from '@/Components/FileUpload';
import { referralSchema } from '@/Schemas/referralSchema';
import useClientValidation from '@/Hooks/useClientValidation';
import { useToast } from '@/Hooks/useToast';

const STEPS = [
    { id: 1, title: 'Select Case', description: 'Choose the case to refer' },
    { id: 2, title: 'Select Agency', description: 'Pick the receiving agency' },
    { id: 3, title: 'Select Service', description: 'Choose services and attach requirements' },
];

function buildServiceRequirementKey(serviceTitle, requirement) {
    return `${serviceTitle}::${requirement}`;
}

const ALLOWED_FILE_TYPES = [
    { ext: '.pdf', mime: 'application/pdf' },
    { ext: '.doc', mime: 'application/msword' },
    { ext: '.docx', mime: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
    { ext: '.jpg', mime: 'image/jpeg' },
    { ext: '.jpeg', mime: 'image/jpeg' },
    { ext: '.png', mime: 'image/png' },
    { ext: '.gif', mime: 'image/gif' },
    { ext: '.webp', mime: 'image/webp' },
];

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

function InfoRow({ label, value, subtext }) {
    return (
        <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{label}</p>
            <p className="mt-1 text-[13px] font-semibold text-slate-700">{value}</p>
            {subtext && <p className="text-[11px] text-slate-500 mt-0.5">{subtext}</p>}
        </div>
    );
}

export default function ReferralCreate({ case_id, agencies, cases: openCases }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        case_id: case_id || '',
        agcy_id: '',
        services: [],
        notes: '',
    });

    const { validate } = useClientValidation(referralSchema, data, setError);

    const toast = useToast();

    const [createStep, setCreateStep] = useState(1);
    const [requirementUploads, setRequirementUploads] = useState({});
    const [fileErrors, setFileErrors] = useState({});
    const [notesValue, setNotesValue] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [complianceMode, setComplianceMode] = useState({});
    const searchDebounceRef = useRef(null);

    const filteredCases = useMemo(() => {
        const q = debouncedSearch.trim().toLowerCase();
        if (!q) return openCases;
        return openCases.filter((c) => {
            const clientName = [c.client?.first_name, c.client?.last_name].filter(Boolean).join(' ').toLowerCase();
            return (c.case_number?.toLowerCase() || '').includes(q) || clientName.includes(q);
        });
    }, [openCases, debouncedSearch]);

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
        return selectedServiceRequirements.filter((item) => !requirementUploads[item.key] && !complianceMode[item.key]).map((item) => item.key);
    }, [selectedServiceRequirements, requirementUploads, complianceMode]);

    const hasMissingRequirementUploads = missingRequirementKeys.length > 0;

    useEffect(() => {
        if (!data.case_id || !data.agcy_id) return;
        const agency = agencies.find((a) => a.id === data.agcy_id);
        if (!agency?.services?.length) return;

        const current = data.services;
        const valid = current.filter((s) => agency.services.some((as) => as.name === s));
        if (valid.length) {
            setData('services', valid);
        } else {
            setData('services', [agency.services[0].name]);
        }
    }, [data.agcy_id]);

    useEffect(() => {
        const activeKeys = new Set(selectedServiceRequirements.map((item) => item.key));
        setRequirementUploads((current) => {
            const next = Object.fromEntries(
                Object.entries(current).filter(([key]) => activeKeys.has(key))
            );
            return Object.keys(next).length === Object.keys(current).length ? current : next;
        });
        setComplianceMode((current) => {
            const next = Object.fromEntries(
                Object.entries(current).filter(([key]) => activeKeys.has(key))
            );
            return Object.keys(next).length === Object.keys(current).length ? current : next;
        });
    }, [selectedServiceRequirements]);

    useEffect(() => {
        if (case_id) {
            setCreateStep(2);
        }
    }, []);

    const isStepOneValid = Boolean(selectedCase);
    const isStepTwoValid = Boolean(data.agcy_id);
    const isStepThreeValid = Boolean(
        data.case_id && data.agcy_id && data.services.length > 0 && !hasMissingRequirementUploads
    );

    const stepProgress = Math.round((createStep / STEPS.length) * 100);

    function goToNextStep() {
        if (createStep === 1 && isStepOneValid) { setCreateStep(2); return; }
        if (createStep === 2 && isStepTwoValid) { setCreateStep(3); }
    }

    function goToPreviousStep() {
        if (createStep === 2) { setCreateStep(1); return; }
        if (createStep === 3) { setCreateStep(2); }
    }

    function toggleServiceSelection(service) {
        setData('services',
            data.services.includes(service)
                ? data.services.filter((item) => item !== service)
                : [...data.services, service]
        );
    }

    function isValidFileType(file) {
        if (!file) return false;
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        const mime = (file.type || '').toLowerCase();
        return ALLOWED_FILE_TYPES.some(
            (t) => t.ext === ext && (!mime || mime === t.mime)
        );
    }

    function handleFileChange(requirementKey, file) {
        if (!file) {
            setFileErrors((current) => {
                const next = { ...current };
                delete next[requirementKey];
                return next;
            });
            setRequirementUploads((current) => {
                const next = { ...current };
                delete next[requirementKey];
                return next;
            });
            return;
        }

        if (!isValidFileType(file)) {
            setFileErrors((current) => ({
                ...current,
                [requirementKey]: 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, GIF, WEBP',
            }));
            return;
        }

        setFileErrors((current) => {
            const next = { ...current };
            delete next[requirementKey];
            return next;
        });
        setRequirementUploads((current) => ({ ...current, [requirementKey]: file }));
        setComplianceMode((current) => ({ ...current, [requirementKey]: false }));
    }

    function parseRequiredDocs(service) {
        if (!service?.requirements) return [];
        return service.requirements.map(r => r.name);
    }

    function submitReferral(e) {
        if (e) e.preventDefault();
        clearErrors();
        if (!validate()) return;
        if (!isStepThreeValid) return;

        const newFileErrors = {};
        let hasInvalidFiles = false;
        Object.entries(requirementUploads).forEach(([key, file]) => {
            if (file && !isValidFileType(file)) {
                newFileErrors[key] = 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, GIF, WEBP';
                hasInvalidFiles = true;
            }
        });

        if (hasInvalidFiles) {
            setFileErrors((current) => ({ ...current, ...newFileErrors }));
            return;
        }

        const complianceEntries = selectedServiceRequirements
            .filter((item) => complianceMode[item.key])
            .map((item) => ({
                service_name: item.serviceTitle,
                requirement_name: item.requirement,
            }));
        if (complianceEntries.length > 0) {
            setData('compliance_requirements', complianceEntries);
        }

        Object.entries(requirementUploads).forEach(([key, file]) => {
            if (file) {
                setData(`documents.${key}`, file);
            }
        });
        setData('notes', notesValue);

        bypassNext();

        if (hasMissingRequirementUploads) {
            toast.warning('Upload all required documents before submitting.');
            return;
        }

        post(route('referrals.store'), {
            onSuccess: () => { toast.success('Referral submitted successfully!'); },
            onError: (errs) => { const msgs = Object.values(errs); toast.error(msgs[0] || 'Failed to submit referral.'); },
        });
    }

    function canProceed() {
        if (createStep === 1) return isStepOneValid;
        if (createStep === 2) return isStepTwoValid;
        return true;
    }

    return (
        <AppLayout title="Refer to Agency">
            <Head title="Refer to Agency" />

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Refer to Agency</h1>
                        <p className="text-sm text-slate-500 mt-1">A guided flow to create a referral to an external agency.</p>
                    </div>
                    <Link href={route('referrals.index')} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0">&larr; Back to Referrals</Link>
                </div>
            </div>

            <form onSubmit={(e) => e.preventDefault()} onKeyDown={(e) => {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                }
            }}>
                <section className="mx-auto flex max-w-6xl overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="w-1/3 min-w-[280px] max-w-[320px] shrink-0 border-r border-slate-200 bg-slate-50/60 p-8">
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
                                        return (
                                            <div key={step.id} className="flex gap-4 group">
                                                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-[12px] font-bold transition-colors ${isCompleted ? 'border-indigo-600 bg-indigo-600 text-white' : isCurrent ? 'bg-white border-indigo-600 text-indigo-600' : 'bg-white border-slate-200 text-slate-400 group-hover:border-slate-400'}`}>
                                                    {isCompleted ? (
                                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                                                            <polyline points="20 6 9 17 4 12" />
                                                        </svg>
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
                                    {createStep === 1 ? 'Pick a case' : createStep === 2 ? 'Choose agency' : 'Finalize referral'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {createStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Select an open case from your list.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Review case details before proceeding.</span></li>
                                        </>
                                    )}
                                    {createStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Choose the agency to refer to.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Services will be loaded based on agency.</span></li>
                                        </>
                                    )}
                                    {createStep === 3 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Select services and upload required documents.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Add optional remarks for the agency.</span></li>
                                        </>
                                    )}
                                </ul>
                                <p className="mt-4 text-[12px] text-slate-500">You can track referral status from the referrals page.</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 flex flex-col p-8">
                        <div className="flex-1">
                            <div className="mb-6 rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-50 via-white to-white p-6">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-indigo-600">Step {createStep}</p>
                                        <h2 className="text-xl font-bold text-slate-800 mt-2">{STEPS[createStep - 1].title}</h2>
                                        <p className="text-[13px] text-slate-500 mt-1">{STEPS[createStep - 1].description}</p>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Progress</p>
                                        <p className="text-[14px] font-bold text-slate-800 mt-1">{stepProgress}% complete</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                {createStep === 1 && (
                                    <div className="space-y-5">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Selection</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Choose the case you want to refer to an agency.</p>
                                            <div className="mt-4">
                                                <Field label="Case" required>
                                                    {openCases.length === 0 ? (
                                                        <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                            <span className="material-symbols-outlined text-[40px] mb-3">folder_off</span>
                                                            <p className="text-[14px] font-medium text-slate-500">No open cases available</p>
                                                            <p className="text-[12px] text-slate-400 mt-1">There are no cases to refer at this time.</p>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <input
                                                                type="text"
                                                                value={searchQuery}
                                                                onChange={(e) => {
                                                                    const value = e.target.value;
                                                                    setSearchQuery(value);
                                                                    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
                                                                    searchDebounceRef.current = setTimeout(() => {
                                                                        setDebouncedSearch(value);
                                                                    }, 300);
                                                                }}
                                                                placeholder="Search by case number or client name..."
                                                                className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />

                                                            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {filteredCases.map((item) => (
                                                                    <button
                                                                        key={item.id}
                                                                        type="button"
                                                                        onClick={() => {
                                                                            setData('case_id', item.id);
                                                                            setData('agcy_id', '');
                                                                            setData('services', []);
                                                                        }}
                                                                        className={`flex w-full flex-col gap-2 rounded-lg border p-4 text-left shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                                                            item.id === data.case_id
                                                                                ? 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50/30'
                                                                                : 'border-slate-200 bg-white hover:border-indigo-400 hover:shadow-md'
                                                                        }`}
                                                                    >
                                                                        <div className="flex items-start justify-between gap-2">
                                                                            <span className="text-[13px] font-bold text-indigo-700">{item.case_number}</span>
                                                                            <div className="flex shrink-0 gap-1.5">
                                                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                                                                                    item.client_type === 'OFW'
                                                                                        ? 'bg-blue-100 text-blue-700'
                                                                                        : 'bg-amber-100 text-amber-700'
                                                                                }`}>
                                                                                    {item.client_type === 'OFW' ? 'OFW' : 'NOK'}
                                                                                </span>
                                                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                                                                                    item.status === 'open' ? 'bg-green-100 text-green-700' :
                                                                                    item.status === 'ongoing' ? 'bg-yellow-100 text-yellow-700' :
                                                                                    'bg-slate-100 text-slate-700'
                                                                                }`}>
                                                                                    {item.status}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                        <p className="text-[13px] font-semibold text-slate-800">
                                                                            {item.client?.first_name || ''} {item.client?.last_name || ''}
                                                                        </p>
                                                                        <p className="text-[11px] text-slate-500">
                                                                            Created: {formatDisplayDate(item.created_at)}
                                                                        </p>
                                                                        {item.summary && (
                                                                            <p className="text-[12px] text-slate-600 line-clamp-2 leading-relaxed">
                                                                                {item.summary}
                                                                            </p>
                                                                        )}
                                                                    </button>
                                                                ))}
                                                            </div>

                                                            {filteredCases.length === 0 && (
                                                                <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                    <span className="material-symbols-outlined text-[40px] mb-3">folder_off</span>
                                                                    <p className="text-[14px] font-medium text-slate-500">No cases found</p>
                                                                    <p className="text-[12px] text-slate-400 mt-1">Try a different search term.</p>
                                                                </div>
                                                            )}
                                                        </>
                                                    )}
                                                </Field>
                                                <InputError message={errors.case_id} className="mt-1" />
                                            </div>
                                        </div>

                                        {selectedCase && (
                                            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Selected Case Details</h3>
                                                <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <InfoRow label="Case Number" value={selectedCase.case_number} />
                                                    <InfoRow label="Client Name" value={`${selectedCase.client?.first_name || ''} ${selectedCase.client?.last_name || ''}`} />
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
                                    </div>
                                )}

                                {createStep === 2 && (
                                    <div className="space-y-5">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Agency Selection</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Choose the agency to handle this referral.</p>
                                            <div className="mt-4">
                                                <Field label="Agency" required>
                                                    <select
                                                        value={data.agcy_id}
                                                        onChange={(e) => {
                                                            const nextAgencyId = e.target.value;
                                                            const agency = agencies.find((a) => a.id === nextAgencyId);
                                                            const nextServices = agency?.services?.map((s) => s.name) || [];
                                                            const valid = data.services.filter((s) => nextServices.includes(s));
                                                            setData('agcy_id', nextAgencyId);
                                                            setData('services', valid.length ? valid : (nextServices.length ? [nextServices[0]] : []));
                                                        }}
                                                        className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        required
                                                    >
                                                        <option value="">Select an agency...</option>
                                                        {agencies.map((agency) => (
                                                            <option key={agency.id} value={agency.id}>
                                                                {agency.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </Field>
                                                <InputError message={errors.agcy_id} className="mt-1" />
                                            </div>
                                        </div>

                                        {selectedCase && (
                                            <div className="rounded-lg border border-indigo-200 bg-indigo-50/30 p-4 shadow-sm">
                                                <div className="flex items-start justify-between gap-2">
                                                    <span className="text-[13px] font-bold text-indigo-700">{selectedCase.case_number}</span>
                                                    <div className="flex shrink-0 gap-1.5">
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                                                            selectedCase.client_type === 'OFW'
                                                                ? 'bg-blue-100 text-blue-700'
                                                                : 'bg-amber-100 text-amber-700'
                                                        }`}>
                                                            {selectedCase.client_type === 'OFW' ? 'OFW' : 'NOK'}
                                                        </span>
                                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                                                            selectedCase.status === 'open' ? 'bg-green-100 text-green-700' :
                                                            selectedCase.status === 'ongoing' ? 'bg-yellow-100 text-yellow-700' :
                                                            'bg-slate-100 text-slate-700'
                                                        }`}>
                                                            {selectedCase.status}
                                                        </span>
                                                    </div>
                                                </div>
                                                <p className="mt-2 text-[13px] font-semibold text-slate-800">
                                                    {selectedCase.client?.first_name || ''} {selectedCase.client?.last_name || ''}
                                                </p>
                                                <p className="mt-2 text-[11px] text-slate-500">
                                                    This case is pre-selected. You can go back to change it.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {createStep === 3 && (
                                    <div className="space-y-5">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Services</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Select one or more services offered by {selectedAgency?.name || 'the agency'}.</p>
                                            <div className="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3">
                                                {availableServices.length ? (
                                                    <div className="space-y-2">
                                                        {availableServices.map((service) => (
                                                            <label key={service} className="flex items-center gap-3 text-[13px] text-slate-700 cursor-pointer py-1">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={data.services.includes(service)}
                                                                    onChange={() => toggleServiceSelection(service)}
                                                                    className="h-4 w-4 rounded border-slate-200 text-indigo-600 focus:ring-indigo-500"
                                                                />
                                                                <span>{service}</span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-[12px] text-slate-500">No available services for this agency.</p>
                                                )}
                                            </div>
                                        </div>
                                        <InputError message={errors.services} className="mt-1" />

                                        {selectedServiceDetails.length > 0 && (
                                            <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Service Requirements</h3>
                                                <p className="mt-2 text-[13px] text-slate-500">Upload required documents for each selected service.</p>
                                                <div className="mt-4 space-y-4">
                                                    {selectedServiceDetails.map((service) => {
                                                        const docs = parseRequiredDocs(service);
                                                        return (
                                                            <div key={service.name} className="rounded-lg border border-slate-200 bg-slate-50/50 p-4">
                                                                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">{service.name}</p>
                                                                <p className="mt-1 text-[12px] text-slate-500">
                                                                    Processing Time: <span className="font-semibold text-indigo-600">{service.processing_days || 'N/A'} business days</span>
                                                                </p>

                                                                {docs.length ? (
                                                                    <div className="mt-3 space-y-2">
                                                                        {docs.map((requirement) => {
                                                                            const requirementKey = buildServiceRequirementKey(service.name, requirement);
                                                                            const hasFile = Boolean(requirementUploads[requirementKey]);
                                                                            const isCompliance = Boolean(complianceMode[requirementKey]);
                                                                            const borderClass = isCompliance
                                                                                ? 'border-orange-200 bg-orange-50/40'
                                                                                : !hasFile
                                                                                    ? 'border-rose-300 bg-rose-50/40'
                                                                                    : 'border-slate-200 bg-white';
                                                                            return (
                                                                                <div key={requirementKey}
                                                                                    className={`rounded-lg border px-4 py-3 ${borderClass}`}
                                                                                >
                                                                                    <p className="text-[12px] font-semibold text-slate-700">{requirement}</p>

                                                                                    <div className="mt-2 flex items-center gap-3">
                                                                                        <label className="flex items-center gap-1.5 cursor-pointer">
                                                                                            <input
                                                                                                type="radio"
                                                                                                name={`upload-mode-${requirementKey}`}
                                                                                                checked={!isCompliance}
                                                                                                onChange={() => {
                                                                                                    setComplianceMode((prev) => ({ ...prev, [requirementKey]: false }));
                                                                                                }}
                                                                                                className="h-3 w-3 text-indigo-600"
                                                                                            />
                                                                                            <span className="text-[11px] text-slate-600">Upload File</span>
                                                                                        </label>
                                                                                        <label className="flex items-center gap-1.5 cursor-pointer">
                                                                                            <input
                                                                                                type="radio"
                                                                                                name={`upload-mode-${requirementKey}`}
                                                                                                checked={isCompliance}
                                                                                                onChange={() => {
                                                                                                    setComplianceMode((prev) => ({ ...prev, [requirementKey]: true }));
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
                                                                                                }}
                                                                                                className="h-3 w-3 text-orange-500"
                                                                                            />
                                                                                            <span className="text-[11px] text-slate-600">For Compliance</span>
                                                                                        </label>
                                                                                    </div>

                                                                                    {isCompliance ? (
                                                                                        <div className="mt-2 inline-flex items-center gap-1.5 rounded-md border border-orange-200 bg-orange-50 px-2.5 py-1">
                                                                                            <svg className="h-3.5 w-3.5 text-orange-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                                                                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
                                                                                            </svg>
                                                                                            <span className="text-[11px] font-bold uppercase tracking-wider text-orange-700">FOR COMPLIANCE</span>
                                                                                        </div>
                                                                                    ) : (
                                                                                        <>
                                                                                            <FileUpload
                                                                                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
                                                                                                label="Choose file"
                                                                                                onFilesSelected={(file) => handleFileChange(requirementKey, file)}
                                                                                            />
                                                                                            {hasFile ? (
                                                                                                <p className="mt-1 text-[11px] text-emerald-600">Attached: {requirementUploads[requirementKey]?.name}</p>
                                                                                            ) : (
                                                                                                <p className="mt-1 text-[11px] text-rose-700">Upload is required for this document.</p>
                                                                                            )}
                                                                                            <InputError message={fileErrors[requirementKey]} className="mt-1" />
                                                                                        </>
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
                                                </div>

                                            </div>
                                        )}

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Remarks</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Add optional context for the receiving agency.</p>
                                            <div className="mt-4">
                                                <textarea
                                                    rows={4}
                                                    value={notesValue}
                                                    onChange={(e) => setNotesValue(e.target.value)}
                                                    placeholder="Optional context for the receiving agency..."
                                                    className="w-full rounded-md border border-slate-200 px-3 py-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                />
                                            </div>
                                            <InputError message={errors.notes} className="mt-1" />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={goToPreviousStep} disabled={createStep === 1}
                                    className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                                </button>
                            </div>
                            {createStep < 3 ? (
                                <button type="button" onClick={goToNextStep} disabled={!canProceed()}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                </button>
                            ) : (
                                <button type="button" onClick={submitReferral} disabled={processing || !isStepThreeValid}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? 'Submitting...' : 'Submit Referral'}
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
