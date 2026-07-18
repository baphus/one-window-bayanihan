import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useEffect, useMemo, useState, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';

import { formatDisplayDate, formatDisplayTime } from '@/lib/utils';
import InputError from '@/Components/InputError';
import { referralSchema } from '@/Schemas/referralSchema';
import useClientValidation from '@/Hooks/useClientValidation';
import { useToast } from '@/Hooks/useToast';

const STEPS = [
    { id: 1, title: 'Select Case', description: 'Choose the case to refer' },
    { id: 2, title: 'Select Agency', description: 'Pick the receiving agency' },
    { id: 3, title: 'Select Service', description: 'Choose services and review requirements' },
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

export default function ReferralCreate({ case_id, agencies, cases: paginatedCases, caseReferrals = {}, filters = {} }) {
    const cases = paginatedCases.data || [];
    const { current_page: currentPage, last_page: lastPage, from, to, total } = paginatedCases.meta || {};
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        case_id: case_id || '',
        agcy_id: '',
        services: [],
        notes: '',
    });

    const { validate } = useClientValidation(referralSchema, data, setError);

    const toast = useToast();

    const [createStep, setCreateStep] = useState(1);
    const [notesValue, setNotesValue] = useState('');
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [debouncedSearch, setDebouncedSearch] = useState(filters.search || '');
    const searchDebounceRef = useRef(null);
    const agencySearchDebounceRef = useRef(null);

    const [agencySearchQuery, setAgencySearchQuery] = useState('');
    const [agencyDebouncedSearch, setAgencyDebouncedSearch] = useState('');

    const selectedCaseRef = useRef(null);

    useEffect(() => {
        if (data.case_id) {
            const found = cases.find((c) => c.id === data.case_id);
            if (found) selectedCaseRef.current = found;
        }
    }, [cases, data.case_id]);

    const filteredAgencies = useMemo(() => {
        const q = agencyDebouncedSearch.trim().toLowerCase();
        const list = !q
            ? agencies
            : agencies.filter((a) => {
                return (a.name?.toLowerCase() || '').includes(q)
                    || (a.short?.toLowerCase() || '').includes(q)
                    || (a.description?.toLowerCase() || '').includes(q);
            });
        return [...list].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    }, [agencies, agencyDebouncedSearch]);

    const initialFormRef = useRef({ case_id: data.case_id, agcy_id: '', services: [] });
    const hasDirty = useMemo(() => (
        data.case_id !== initialFormRef.current.case_id
        || data.agcy_id !== initialFormRef.current.agcy_id
        || JSON.stringify(data.services) !== JSON.stringify(initialFormRef.current.services)
        || notesValue !== ''
    ), [data.case_id, data.agcy_id, data.services, notesValue]);
    const { UnsavedModal, bypassNext } = useUnsavedChanges(hasDirty);
    const selectedCase = useMemo(() => {
        if (!data.case_id) return null;
        return cases.find((c) => c.id === data.case_id) || selectedCaseRef.current;
    }, [cases, data.case_id]);
    const selectedAgency = agencies.find((item) => item.id === data.agcy_id);

    // Agencies that already have a referral for the selected case
    const referredAgencyIds = useMemo(() => {
        if (!data.case_id || !caseReferrals[data.case_id]) return new Set();
        return new Set(caseReferrals[data.case_id]);
    }, [data.case_id, caseReferrals]);

    const selectedAgencyIsReferred = data.agcy_id && referredAgencyIds.has(data.agcy_id);

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
                key: `${service.name}::${req.name}`,
                serviceTitle: service.name,
                requirement: req.name,
            }))
        );
    }, [selectedServiceDetails]);

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
        if (case_id) {
            setCreateStep(2);
        }
    }, []);

    const prevSearchRef = useRef(debouncedSearch);
    useEffect(() => {
        const prev = prevSearchRef.current;
        prevSearchRef.current = debouncedSearch;
        if (debouncedSearch === prev) return;

        router.get(route('referrals.create'), {
            search: debouncedSearch || null,
            case_id: data.case_id || null,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, [debouncedSearch]);

    const isStepOneValid = Boolean(selectedCase);
    const isStepTwoValid = Boolean(data.agcy_id) && !selectedAgencyIsReferred;
    const isStepThreeValid = Boolean(
        data.case_id && data.agcy_id && data.services.length > 0
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

    function parseRequiredDocs(service) {
        if (!service?.requirements) return [];
        return service.requirements.map(r => r.name);
    }

    function submitReferral(e) {
        if (e) e.preventDefault();
        clearErrors();
        if (!validate()) return;
        if (!isStepThreeValid) return;

        setData('notes', notesValue);

        bypassNext();

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
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Select services and review requirements.</span></li>
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
                                                    {total === 0 ? (
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
                                                                placeholder="Search by tracking number or client name..."
                                                                className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />

                                                            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {cases.map((item) => (
                                                                    <button
                                                                        key={item.id}
                                                                        type="button"
                                                                        onClick={() => {
                                                                            setData('case_id', item.id);
                                                                            setData('agcy_id', '');
                                                                            setData('services', []);
                                                                            setNotesValue('');
                                                                        }}
                                                                        className={`flex w-full flex-col gap-2 rounded-lg border p-4 text-left shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                                                            item.id === data.case_id
                                                                                ? 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50/30'
                                                                                : 'border-slate-200 bg-white hover:border-indigo-400 hover:shadow-md'
                                                                        }`}
                                                                    >
                                                                        <div className="flex items-start justify-between gap-2">
                                                                            <div className="min-w-0">
                                                                                <span className="text-[13px] font-bold text-indigo-700">{item.case_number}</span>
                                                                                {item.tracker_number && (
                                                                                    <p className="text-[11px] text-indigo-500/70 mt-0.5 font-mono">ID: {item.tracker_number}</p>
                                                                                )}
                                                                            </div>
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

                                                            {cases.length === 0 && (
                                                                <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                    <span className="material-symbols-outlined text-[40px] mb-3">folder_off</span>
                                                                    <p className="text-[14px] font-medium text-slate-500">No cases found</p>
                                                                    <p className="text-[12px] text-slate-400 mt-1">Try a different search term.</p>
                                                                </div>
                                                            )}

                                                            {lastPage > 1 && (
                                                                <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                                                                    <p className="text-[11px] text-slate-500">
                                                                        Showing {from}–{to} of {total} cases
                                                                    </p>
                                                                    <div className="flex items-center gap-2">
                                                                        <button
                                                                            type="button"
                                                                            disabled={currentPage <= 1}
                                                                            onClick={() => {
                                                                                router.get(route('referrals.create'), {
                                                                                    search: debouncedSearch || null,
                                                                                    page: currentPage - 1,
                                                                                    case_id: data.case_id || null,
                                                                                }, {
                                                                                    preserveState: true,
                                                                                    preserveScroll: true,
                                                                                    replace: true,
                                                                                });
                                                                            }}
                                                                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                                        >
                                                                            <span className="material-symbols-outlined text-[14px]">chevron_left</span>
                                                                            Prev
                                                                        </button>
                                                                        <div className="flex items-center gap-1">
                                                                            {Array.from({ length: Math.min(lastPage, 5) }, (_, i) => {
                                                                                let pageNum;
                                                                                if (lastPage <= 5) {
                                                                                    pageNum = i + 1;
                                                                                } else if (currentPage <= 3) {
                                                                                    pageNum = i + 1;
                                                                                } else if (currentPage >= lastPage - 2) {
                                                                                    pageNum = lastPage - 4 + i;
                                                                                } else {
                                                                                    pageNum = currentPage - 2 + i;
                                                                                }
                                                                                return (
                                                                                    <button
                                                                                        key={pageNum}
                                                                                        type="button"
                                                                                        onClick={() => {
                                                                                            router.get(route('referrals.create'), {
                                                                                                search: debouncedSearch || null,
                                                                                                page: pageNum,
                                                                                                case_id: data.case_id || null,
                                                                                            }, {
                                                                                                preserveState: true,
                                                                                                preserveScroll: true,
                                                                                                replace: true,
                                                                                            });
                                                                                        }}
                                                                                        className={`flex h-7 w-7 items-center justify-center rounded-md text-[12px] font-semibold transition ${
                                                                                            pageNum === currentPage
                                                                                                ? 'bg-indigo-600 text-white'
                                                                                                : 'text-slate-600 hover:bg-slate-100'
                                                                                        }`}
                                                                                    >
                                                                                        {pageNum}
                                                                                    </button>
                                                                                );
                                                                            })}
                                                                        </div>
                                                                        <button
                                                                            type="button"
                                                                            disabled={currentPage >= lastPage}
                                                                            onClick={() => {
                                                                                router.get(route('referrals.create'), {
                                                                                    search: debouncedSearch || null,
                                                                                    page: currentPage + 1,
                                                                                    case_id: data.case_id || null,
                                                                                }, {
                                                                                    preserveState: true,
                                                                                    preserveScroll: true,
                                                                                    replace: true,
                                                                                });
                                                                            }}
                                                                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-[12px] font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                                        >
                                                                            Next
                                                                            <span className="material-symbols-outlined text-[14px]">chevron_right</span>
                                                                        </button>
                                                                    </div>
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
                                                    {agencies.length === 0 ? (
                                                        <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                            <span className="material-symbols-outlined text-[40px] mb-3">business_off</span>
                                                            <p className="text-[14px] font-medium text-slate-500">No agencies available</p>
                                                            <p className="text-[12px] text-slate-400 mt-1">There are no agencies to refer to at this time.</p>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <input
                                                                type="text"
                                                                value={agencySearchQuery}
                                                                onChange={(e) => {
                                                                    const value = e.target.value;
                                                                    setAgencySearchQuery(value);
                                                                    if (agencySearchDebounceRef.current) clearTimeout(agencySearchDebounceRef.current);
                                                                    agencySearchDebounceRef.current = setTimeout(() => {
                                                                        setAgencyDebouncedSearch(value);
                                                                    }, 300);
                                                                }}
                                                                placeholder="Search by agency name..."
                                                                className="h-10 w-full rounded-md border border-slate-200 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />

                                                            <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {filteredAgencies.map((agency) => {
                                                                    const alreadyReferred = data.case_id && referredAgencyIds.has(agency.id);
                                                                    return (
                                                                        <button
                                                                            key={agency.id}
                                                                            type="button"
                                                                            onClick={() => {
                                                                                if (alreadyReferred) return;
                                                                                const nextServices = agency.services?.map((s) => s.name) || [];
                                                                                const valid = data.services.filter((s) => nextServices.includes(s));
                                                                                setData('agcy_id', agency.id);
                                                                                setData('services', valid.length ? valid : (nextServices.length ? [nextServices[0]] : []));
                                                                            }}
                                                                            disabled={alreadyReferred}
                                                                            className={`flex w-full flex-col gap-2 rounded-lg border p-4 text-left shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                                                                alreadyReferred
                                                                                    ? 'border-slate-200 bg-slate-50 opacity-50 cursor-not-allowed'
                                                                                    : agency.id === data.agcy_id
                                                                                        ? 'ring-2 ring-indigo-500 border-indigo-500 bg-indigo-50/30'
                                                                                        : 'border-slate-200 bg-white hover:border-indigo-400 hover:shadow-md'
                                                                            }`}
                                                                        >
                                                                            <div className="flex items-center gap-3">
                                                                                {agency.logo_url ? (
                                                                                    <img
                                                                                        src={agency.logo_url}
                                                                                        alt={agency.name}
                                                                                        className="h-9 w-9 shrink-0 rounded-full object-contain border border-slate-200"
                                                                                    />
                                                                                ) : (
                                                                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-[13px] font-bold">
                                                                                        {(agency.name || '?').charAt(0).toUpperCase()}
                                                                                    </div>
                                                                                )}
                                                                                <div className="flex-1 min-w-0">
                                                                                    <div className="flex items-start justify-between gap-2">
                                                                                        <span className="text-[13px] font-bold text-indigo-700 truncate">{agency.name}</span>
                                                                                        {agency.short && (
                                                                                            <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600 shrink-0">
                                                                                                {agency.short}
                                                                                            </span>
                                                                                        )}
                                                                                    </div>
                                                                                    {agency.description && (
                                                                                        <p className="text-[12px] text-slate-600 line-clamp-2 leading-relaxed mt-0.5">
                                                                                            {agency.description}
                                                                                        </p>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                            <div className="flex items-center gap-2 mt-1">
                                                                                <span className="text-[11px] text-slate-500">
                                                                                    {agency.services?.length || 0} service{(agency.services?.length || 0) !== 1 ? 's' : ''}
                                                                                </span>
                                                                                {alreadyReferred && (
                                                                                    <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                                                                        Already referred
                                                                                    </span>
                                                                                )}
                                                                            </div>
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>

                                                            {filteredAgencies.length === 0 && (
                                                                <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                    <span className="material-symbols-outlined text-[40px] mb-3">search_off</span>
                                                                    <p className="text-[14px] font-medium text-slate-500">No agencies found</p>
                                                                    <p className="text-[12px] text-slate-400 mt-1">Try a different search term.</p>
                                                                </div>
                                                            )}
                                                        </>
                                                    )}
                                                </Field>
                                                {selectedAgencyIsReferred && (
                                                    <p className="mt-2 text-[12px] font-medium text-amber-700 flex items-center gap-1.5">
                                                        <span className="material-symbols-outlined text-[16px]">warning</span>
                                                        This case has already been referred to {selectedAgency?.name}. Select a different agency.
                                                    </p>
                                                )}
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
                                                <p className="mt-2 text-[13px] text-slate-500">Required documents for each selected service.</p>
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
                                                                    <ul className="mt-3 space-y-1.5">
                                                                        {docs.map((requirement, idx) => (
                                                                            <li key={idx} className="flex items-start gap-2 text-[12px] text-slate-700">
                                                                                <span className="material-symbols-outlined text-[14px] text-slate-400 mt-0.5 shrink-0">chevron_right</span>
                                                                                <span>{requirement}</span>
                                                                            </li>
                                                                        ))}
                                                                    </ul>
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
            {UnsavedModal}
        </AppLayout>
    );
}
