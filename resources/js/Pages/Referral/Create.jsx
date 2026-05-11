import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const STEPS = [
    { index: 1, label: 'Select Case' },
    { index: 2, label: 'Select Agency' },
    { index: 3, label: 'Select Service' },
];

function buildServiceRequirementKey(serviceTitle, requirement) {
    return `${serviceTitle}::${requirement}`;
}

function FieldLabel({ label, children, full }) {
    return (
        <div className={full ? 'md:col-span-2' : ''}>
            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">{label}</label>
            {children}
        </div>
    );
}

function InfoRow({ label, value }) {
    return (
        <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{label}</p>
            <p className="mt-1 text-[13px] font-semibold text-slate-700">{value}</p>
        </div>
    );
}

export default function ReferralCreate({ case_id, agencies, cases }) {
    const { data, setData, post, processing, errors } = useForm({
        case_id: case_id || '',
        agcy_id: '',
        services: [],
        notes: '',
    });

    const [createStep, setCreateStep] = useState(1);
    const [requirementUploads, setRequirementUploads] = useState({});
    const [notesValue, setNotesValue] = useState('');

    const openCases = cases || [];
    const initialAgencyId = agencies[0]?.id || '';
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
            (service.pivot?.required_documents || []).map((requirement) => ({
                key: buildServiceRequirementKey(service.name, requirement),
                serviceTitle: service.name,
                requirement,
            }))
        );
    }, [selectedServiceDetails]);

    const missingRequirementKeys = useMemo(() => {
        return selectedServiceRequirements.filter((item) => !requirementUploads[item.key]).map((item) => item.key);
    }, [selectedServiceRequirements, requirementUploads]);

    const hasMissingRequirementUploads = missingRequirementKeys.length > 0;

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
    }, [selectedServiceRequirements]);

    const isStepOneValid = Boolean(selectedCase);
    const isStepTwoValid = Boolean(data.agcy_id);
    const isStepThreeValid = Boolean(
        data.case_id && data.agcy_id && data.services.length > 0 && !hasMissingRequirementUploads
    );

    function goToNextStep() {
        if (createStep === 1 && isStepOneValid) { setCreateStep(2); return; }
        if (createStep === 2 && isStepTwoValid) { setCreateStep(3); }
    }

    function goToPreviousStep() {
        if (createStep === 2) { setCreateStep(1); return; }
        if (createStep === 3) { setCreateStep(2); }
    }

    function toggleServiceSelection(service) {
        setData('services', (current) =>
            current.includes(service)
                ? current.filter((item) => item !== service)
                : [...current, service]
        );
    }

    function handleFileChange(requirementKey, file) {
        setRequirementUploads((current) => ({ ...current, [requirementKey]: file }));
    }

    function parseRequiredDocs(pivot) {
        if (!pivot?.required_documents) return [];
        if (Array.isArray(pivot.required_documents)) return pivot.required_documents;
        try { return JSON.parse(pivot.required_documents); } catch { return []; }
    }

    function submitReferral(e) {
        e.preventDefault();
        if (!isStepThreeValid) return;

        Object.entries(requirementUploads).forEach(([key, file]) => {
            if (file) {
                setData(`documents.${key}`, file);
            }
        });
        setData('notes', notesValue);

        setTimeout(() => {
            post(route('referrals.store'), {
                onSuccess: () => { },
                onError: () => { },
            });
        }, 0);
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
                <h1 className="text-2xl font-bold text-slate-900">New Referral</h1>
                <p className="text-sm text-slate-500 mt-1">Complete each step to create a referral.</p>
            </div>

            <form onSubmit={submitReferral}>
                <section className="rounded-lg border border-[#cbd5e1] bg-white shadow-sm">
                    <div className="border-b border-[#e2e8f0] px-5 py-4">
                        <div className="grid grid-cols-3 gap-2">
                            {STEPS.map((step) => {
                                const isActive = createStep === step.index;
                                const isDone = createStep > step.index;
                                return (
                                    <div key={step.index}
                                        className={`rounded-[3px] border px-3 py-2 text-center text-[11px] font-bold ${isActive ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : isDone ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-[#e2e8f0] bg-slate-50 text-slate-500'}`}
                                    >
                                        {step.label}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
                        {createStep === 1 && (
                            <>
                                <FieldLabel label="Case" full>
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
                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                    >
                                        {openCases.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.case_number} - {item.client?.first_name} {item.client?.last_name}
                                            </option>
                                        ))}
                                        {openCases.length === 0 && <option value="">No open cases available</option>}
                                    </select>
                                </FieldLabel>

                                {selectedCase && (
                                    <div className="rounded-[3px] border border-[#e2e8f0] bg-slate-50 px-3 py-3 md:col-span-2">
                                        <p className="mb-3 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">Selected Case Details</p>
                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <InfoRow label="Case Number" value={selectedCase.case_number} />
                                            <InfoRow label="Client Name" value={`${selectedCase.client?.first_name || ''} ${selectedCase.client?.last_name || ''}`} />
                                            <InfoRow label="Client Type" value={selectedCase.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'} />
                                            <InfoRow label="Status" value={selectedCase.status} />
                                            <InfoRow label="Date Created" value={new Date(selectedCase.created_at).toLocaleDateString()} />
                                            {selectedCase.summary && (
                                                <div className="md:col-span-2">
                                                    <InfoRow label="Case Narrative" value={selectedCase.summary} />
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {createStep === 2 && (
                            <>
                                <FieldLabel label="Agency" full>
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
                                            <option key={agency.id} value={agency.id}>
                                                {agency.name}
                                            </option>
                                        ))}
                                    </select>
                                </FieldLabel>

                                {selectedCase && (
                                    <div className="rounded-[3px] border border-[#e2e8f0] bg-slate-50 px-3 py-2 text-[12px] text-slate-600 md:col-span-2">
                                        Selected case: <span className="font-semibold text-slate-800">{selectedCase.client?.first_name} {selectedCase.client?.last_name}</span> ({selectedCase.case_number})
                                    </div>
                                )}
                            </>
                        )}

                        {createStep === 3 && (
                            <>
                                <FieldLabel label="Services" full>
                                    <div className="rounded-[3px] border border-[#cbd5e1] bg-white px-3 py-2">
                                        {availableServices.length ? (
                                            <div className="space-y-2">
                                                {availableServices.map((service) => (
                                                    <label key={service} className="flex items-center gap-2 text-[13px] text-slate-700 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.services.includes(service)}
                                                            onChange={() => toggleServiceSelection(service)}
                                                            className="h-4 w-4 rounded border-[#cbd5e1] text-indigo-600 focus:ring-indigo-500"
                                                        />
                                                        <span>{service}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-[12px] text-slate-500">No available services for this stakeholder.</p>
                                        )}
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-500">Select one or more services.</p>
                                </FieldLabel>

                                <FieldLabel label="Service Requirements" full>
                                    <div className="rounded-[3px] border border-[#e2e8f0] bg-slate-50 px-3 py-2">
                                        {selectedServiceDetails.length ? (
                                            <div className="space-y-3">
                                                {selectedServiceDetails.map((service) => {
                                                    const docs = parseRequiredDocs(service.pivot);
                                                    return (
                                                        <div key={service.name} className="rounded-[3px] border border-[#dbe5ef] bg-white px-3 py-3">
                                                            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{service.name}</p>
                                                            <p className="mt-1 text-[12px] font-semibold text-slate-700">
                                                                Processing Time: <span className="text-indigo-600">{service.pivot?.processing_days || 'N/A'} business days</span>
                                                            </p>

                                                            {docs.length ? (
                                                                <div className="mt-3 space-y-2">
                                                                    {docs.map((requirement) => {
                                                                        const requirementKey = buildServiceRequirementKey(service.name, requirement);
                                                                        const hasFile = Boolean(requirementUploads[requirementKey]);
                                                                        return (
                                                                            <div key={requirementKey}
                                                                                className={`rounded-[3px] border px-3 py-2 ${!hasFile ? 'border-rose-300 bg-rose-50/40' : 'border-[#dbe5ef] bg-white'}`}
                                                                            >
                                                                                <p className="text-[12px] font-semibold text-slate-700">{requirement}</p>
                                                                                <input
                                                                                    type="file"
                                                                                    onChange={(e) => handleFileChange(requirementKey, e.target.files?.[0] || null)}
                                                                                    className="mt-2 block w-full rounded-[3px] border border-[#cbd5e1] bg-white px-3 py-2 text-[12px] text-slate-700 file:mr-3 file:rounded-[3px] file:border-0 file:bg-indigo-50 file:px-3 file:py-1 file:text-[11px] file:font-semibold file:text-indigo-700"
                                                                                />
                                                                                {hasFile ? (
                                                                                    <p className="mt-1 text-[11px] text-slate-500">Attached: {requirementUploads[requirementKey]?.name}</p>
                                                                                ) : (
                                                                                    <p className="mt-1 text-[11px] text-rose-700">Upload is required for this document.</p>
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
                                        ) : (
                                            <p className="text-[12px] text-slate-500">Select services above to see their requirements.</p>
                                        )}

                                        {hasMissingRequirementUploads && (
                                            <div className="mt-3 rounded-[3px] border border-rose-200 bg-rose-50 px-3 py-2 text-[12px] text-rose-800">
                                                Missing uploads: {missingRequirementKeys.length}. Attach one file for each required service document to continue.
                                            </div>
                                        )}
                                    </div>
                                </FieldLabel>

                                <FieldLabel label="Remarks" full>
                                    <textarea
                                        rows={4}
                                        value={notesValue}
                                        onChange={(e) => setNotesValue(e.target.value)}
                                        placeholder="Optional context for the receiving agency"
                                        className="w-full rounded-[3px] border border-[#cbd5e1] px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                    />
                                </FieldLabel>
                            </>
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t border-[#e2e8f0] px-5 py-3">
                        <div>
                            {errors.services && <p className="text-[12px] text-red-600">{errors.services}</p>}
                            {errors.case_id && <p className="text-[12px] text-red-600">{errors.case_id}</p>}
                            {errors.agcy_id && <p className="text-[12px] text-red-600">{errors.agcy_id}</p>}
                        </div>
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('referrals.index')}
                                className="inline-flex items-center rounded-[3px] border border-[#cbd5e1] bg-white px-4 py-2 text-[12px] font-bold text-slate-700 hover:bg-slate-50"
                            >
                                Cancel
                            </Link>

                            {createStep > 1 && (
                                <button
                                    type="button"
                                    onClick={goToPreviousStep}
                                    className="inline-flex items-center rounded-[3px] border border-[#cbd5e1] bg-white px-4 py-2 text-[12px] font-bold text-slate-700 hover:bg-slate-50"
                                >
                                    Back
                                </button>
                            )}

                            {createStep < 3 ? (
                                <button
                                    type="button"
                                    onClick={goToNextStep}
                                    disabled={(createStep === 1 && !isStepOneValid) || (createStep === 2 && !isStepTwoValid)}
                                    className="inline-flex items-center rounded-[3px] bg-indigo-600 px-4 py-2 text-[12px] font-bold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Next
                                </button>
                            ) : (
                                <button
                                    type="submit"
                                    disabled={processing || !isStepThreeValid}
                                    className="inline-flex items-center rounded-[3px] bg-indigo-600 px-4 py-2 text-[12px] font-bold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {processing ? 'Submitting...' : 'Submit Referral'}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </form>
        </AppLayout>
    );
}
