import NativeSelect from '@/Components/Select';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef, lazy, Suspense } from 'react';

import CountrySelect from '@/Components/CountrySelect';
import PhoneInput from '@/Components/PhoneInput';
import SearchableSelect from '@/Components/SearchableSelect';
import InputError from '@/Components/InputError';
import { useToast } from '@/Hooks/useToast';
import { DEFAULT_OCCUPATIONS } from '@/data/defaultOccupations';

const STEPS = [
    { id: 1, title: 'Client Profile', description: 'Enter client information and employment details' },
    { id: 2, title: 'Case Setup', description: 'Define case parameters and tracking' },
    { id: 3, title: 'Case Narrative', description: 'Document client situation' },
];

const SUFFIX_OPTIONS = ['', 'Jr', 'Sr', 'II', 'III', 'IV', 'V'];

// The address dropdowns pull in the ~1.6 MB PSGC dataset — lazy-split them so
// the dataset is not in the initial chunk. The province lookup below resolves
// from the same lazily-loaded module via a cached ref.
const AddressDropdowns = lazy(() => import('@/Components/AddressDropdowns'));
// Same dataset chain via lib/addressResolver — only needed for the confirm modal.

function AddressLoadingFallback() {
    return <p className="text-xs text-slate-400">Loading address options…</p>;
}

// Applicants must be 15–100 years old (ISO date strings compare chronologically).
const DOB_BOUNDS = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');

    return {
        min: `${now.getFullYear() - 100}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
        max: `${now.getFullYear() - 15}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
    };
})();

// Today (ISO date string) — employment start/end dates may be today but not later.
const TODAY = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
})();

// Employment cannot start before the worker reaches minimum working age (15),
// matching the DOB floor. Without this a 21-year-old could report a 10-year span.
const WORKING_AGE_FLOOR_YEARS = 15;
function employmentStartFloor(dob) {
    if (!dob) return '';
    const [y, m, d] = dob.split('-').map(Number);
    const floor = new Date(y + WORKING_AGE_FLOOR_YEARS, m - 1, d);
    const pad = (n) => String(n).padStart(2, '0');
    return `${floor.getFullYear()}-${pad(floor.getMonth() + 1)}-${pad(floor.getDate())}`;
}

// The form always sends category_ids.
function normalizeCategoryIds(value) {
    const values = Array.isArray(value) ? value : (value == null || value === '' ? [] : [value]);
    return [...new Set(values.map((item) => (item && typeof item === 'object' ? item.id : item))
        .filter((id) => id !== null && id !== undefined && id !== '').map(String))];
}

function normalizeDateInput(value) {
    if (!value) return '';
    const match = String(value).match(/^\d{4}-\d{2}-\d{2}/);
    return match ? match[0] : '';
}

function normalizeSelectedNokIndex(value) {
    return value === null || value === undefined || value === '' ? '' : String(value);
}

function buildEmployment(value = {}) {
    const employment = value && typeof value === 'object' ? value : {};
    const endDate = normalizeDateInput(employment.end_date);
    const hasExplicitPresent = Object.prototype.hasOwnProperty.call(employment, 'is_present');
    const isPresent = hasExplicitPresent
        ? employment.is_present === true
        : Object.keys(employment).length > 0 && !endDate;

    return {
        employer_name: employment.employer_name || '',
        position: employment.position || '',
        country: employment.country || '',
        start_date: normalizeDateInput(employment.start_date),
        end_date: isPresent ? '' : endDate,
        last_country: employment.last_country || '',
        last_position: employment.last_position || '',
        date_of_arrival: normalizeDateInput(employment.date_of_arrival),
        is_present: isPresent,
    };
}

// The case number and tracker number are assigned by the server on save
// (CaseService::generateCaseNumber / generateTrackerNumber) using the canonical
// OWB-{YEAR}{MONTH}-{NNNNN} and OWBAP-XXXXXXXXXX formats. This screen used to generate and
// display look-alike values (CM-YYYYMMDD-NNNN plus a random OWBAP token) which
// were then thrown away, so a case manager who noted the tracker number to give
// the client handed over an identifier that did not exist. Show a placeholder
// instead of inventing one.
const PENDING_IDENTIFIER = 'Assigned on save';

function Field({ label, required, children, className }) {
    return (
        <div className={`min-w-0 ${className || ''}`}>
            <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}{required ? <span className="text-red-500 ml-0.5">*</span> : ''}
            </label>
            {children}
        </div>
    );
}

function Subsection({ title, children }) {
    return (
        <div className="space-y-2.5">
            <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">{title}</h4>
            {children}
        </div>
    );
}

function Input({ value, onChange, placeholder, type = 'text', maxLength, minLength, readOnly, required, onBlur, min, max, className = '' }) {
    return (
        <input
            type={type}
            value={value}
            onChange={onChange}
            onBlur={onBlur}
            placeholder={placeholder}
            maxLength={maxLength}
            minLength={minLength}
            readOnly={readOnly}
            required={required}
            min={min}
            max={max}
            className={`h-10 w-full rounded-md border border-outline-variant px-3 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40 ${readOnly ? 'bg-slate-50' : ''} ${className}`}
        />
    );
}

function SelectField({ value, onChange, options, placeholder, required }) {
    return (
        <NativeSelect
            value={value}
            onChange={onChange}
            required={required}
            className="h-10 w-full px-3 py-2 text-[13px]"
        >
            {placeholder && <option value="">{placeholder}</option>}
            {options.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
        </NativeSelect>
    );
}

function CategoryCheckboxDropdown({ categories, selectedIds, onChange, error }) {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef(null);
    const panelRef = useRef(null);
    const listboxId = 'category-checkbox-listbox';

    useEffect(() => {
        if (!open) return;
        function handlePointerDown(e) {
            if (
                panelRef.current && !panelRef.current.contains(e.target) &&
                triggerRef.current && !triggerRef.current.contains(e.target)
            ) {
                setOpen(false);
            }
        }
        function handleKeyDown(e) {
            if (e.key === 'Escape') {
                setOpen(false);
                triggerRef.current?.focus();
            }
        }
        document.addEventListener('mousedown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('mousedown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [open]);

    function toggle(id) {
        const sid = String(id);
        const next = selectedIds.includes(sid)
            ? selectedIds.filter((x) => x !== sid)
            : [...selectedIds, sid];
        onChange(next);
    }

    const count = selectedIds.length;
    let summary;
    if (count === 0) {
        summary = 'Select categories\u2026';
    } else if (count <= 2) {
        summary = selectedIds
            .map((id) => categories.find((c) => String(c.id) === String(id))?.name || id)
            .join(', ');
    } else {
        summary = `${count} categories selected`;
    }

    return (
        <div className="relative">
            <button
                ref={triggerRef}
                type="button"
                role="combobox"
                aria-label="Case categories"
                aria-expanded={open}
                aria-controls={listboxId}
                aria-haspopup="listbox"
                onClick={() => setOpen((v) => !v)}
                className={`flex h-10 w-full items-center justify-between gap-2 rounded-[3px] border px-3 text-left text-[13px] outline-none transition-colors bg-white ${
                    error
                        ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                        : 'border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
                } ${count === 0 ? 'text-slate-400' : 'text-slate-700'}`}
            >
                <span className="truncate">{summary}</span>
                <span className={`material-symbols-outlined shrink-0 text-[16px] text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true">expand_more</span>
            </button>
            {open && (
                <div
                    ref={panelRef}
                    id={listboxId}
                    role="listbox"
                    aria-multiselectable="true"
                    aria-label="Categories"
                    className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg focus:outline-none owb-scroll-wide"
                >
                    {categories.map((cat) => {
                        const checked = selectedIds.includes(String(cat.id));
                        return (
                            <div
                                key={cat.id}
                                role="option"
                                aria-selected={checked}
                                onClick={() => toggle(cat.id)}
                                onKeyDown={(e) => {
                                    if (e.key === ' ' || e.key === 'Enter') {
                                        e.preventDefault();
                                        toggle(cat.id);
                                    }
                                }}
                                tabIndex={-1}
                                className={`flex cursor-pointer items-center gap-2 px-3 py-1.5 text-[12px] transition-colors ${
                                    checked ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <div className={`flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors ${
                                    checked ? 'border-primary bg-primary' : 'border-outline-variant bg-white'
                                }`}>
                                    {checked && (
                                        <svg className="h-3 w-3 text-white" viewBox="0 0 12 12" fill="none">
                                            <path d="M2.5 6L5 8.5L9.5 3.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                        </svg>
                                    )}
                                </div>
                                {cat.color && (
                                    <span
                                        className="h-2 w-2 shrink-0 rounded-full"
                                        style={{ backgroundColor: cat.color }}
                                    />
                                )}
                                <span className="truncate">{cat.name}</span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function CaseSummaryModal({ show, data, caseId, trackingId, categories, caseIssues, notificationEmail, resolvedAddress, onClose, onConfirm, processing, nokSummary }) {
    if (!show) return null;

    const selectedCategories = normalizeCategoryIds(data.category_ids)
        .map((id) => categories.find(c => String(c.id) === id)).filter(Boolean);
    const issueName = caseIssues.find(i => String(i.id) === String(data.case_issue_id))?.name || '—';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="relative z-10 w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl owb-modal-animate">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900">Confirm Case Creation</h3>
                        <p className="text-[13px] text-slate-500 mt-0.5">Review the details below before creating the case.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-md p-1.5 text-slate-400 hover:text-slate-600 transition-colors">
                        <span className="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div className="max-h-[60vh] overflow-y-auto px-6 py-5 space-y-4 owb-scroll-wide">
                    <div className="grid grid-cols-2 gap-4 text-[13px]">
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Case No.</span>
                            <span className="font-semibold text-slate-800">{caseId}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Tracking ID</span>
                            <span className="font-semibold text-slate-800">{trackingId}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Client Type</span>
                            <span className="font-semibold text-slate-800">{data.client_type === 'OFW' ? 'Overseas Filipino Worker' : 'Next of Kin'}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Client Name</span>
                            <span className="font-semibold text-slate-800">{[data.client.first_name, data.client.last_name].filter(Boolean).join(' ') || '—'}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Email</span>
                            <span className="font-semibold text-slate-800">{notificationEmail || '—'}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Contact Number</span>
                            <span className="font-semibold text-slate-800">{data.client.contact_number || '—'}</span>
                        </div>
                        <div className="col-span-2">
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Address</span>
                            <span className="font-semibold text-slate-800">
                                {resolvedAddress || '—'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Category</span>
                            <span className="flex flex-wrap gap-1">
                                {selectedCategories.length ? selectedCategories.map((category) => (
                                    <span key={category.id} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {category.color && <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: category.color }} />}
                                        {category.name}
                                    </span>
                                )) : '—'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Case Issue</span>
                            <span className="font-semibold text-slate-800">{issueName}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Vulnerability Status</span>
                            <span className="font-semibold text-slate-800">{data.vulnerability_indicator || '—'}</span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Next of Kin</span>
                            <span className="font-semibold text-slate-800">{nokSummary}</span>
                        </div>
                    </div>

                    {data.summary && (
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Narrative / Summary</span>
                            <p className="text-[13px] text-slate-700 leading-relaxed whitespace-pre-wrap bg-slate-50 rounded-lg border border-slate-200 p-3">
                                {data.summary}
                            </p>
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between border-t border-slate-200 px-6 py-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 px-2 text-[12px] font-bold text-slate-500 hover:text-slate-700 transition-colors"
                    >
                        Go Back &amp; Edit
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="inline-flex items-center gap-2 h-9 rounded-md bg-primary px-5 text-[12px] font-bold text-white hover:bg-primary-container transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span className="material-symbols-outlined text-[16px]">check_circle</span>
                        {processing ? 'Creating...' : 'Confirm & Create Case'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function CaseCreate() {
    const { client, categories = [], caseIssues = [], occupationOptions = [] } = usePage().props;
    // Public OFW self-filing pre-fills the filer's linked client record.
    const isOwnClientFiler = client?.type === 'OFW' && !!client?.id;

    // Merge curated defaults with previously entered occupations from the backend,
    // de-duplicate, sort, and shape as [value, label] pairs for SearchableSelect.
    const mergedOccupationOptions = useMemo(() => {
        const labels = [...DEFAULT_OCCUPATIONS, ...occupationOptions.map((o) => o.label ?? o)]
            .map((p) => String(p).trim())
            .filter(Boolean);
        const unique = [...new Set(labels)].sort((a, b) => a.localeCompare(b));
        return unique.map((p) => ({ value: p, label: p }));
    }, [occupationOptions]);

    function normalizeSex(value) {
        if (!value) return '';
        const upper = value.toUpperCase();
        if (upper === 'MALE') return 'Male';
        if (upper === 'FEMALE') return 'Female';
        return value;
    }

    const { data, setData, post, transform, processing, errors, setError, clearErrors } = useForm({
        client_type: 'OFW',
        category_ids: [],
        vulnerability_indicator: 'None',
        nok_vulnerability_indicator: 'None',
        summary: '',
        client: {
            first_name: '',
            last_name: '',
            middle_name: '',
            suffix: '',
            date_of_birth: '',
            sex: 'Male',
            email: '',
            contact_number: '',
        },
        address: {
            region: '0700000000',
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
            is_present: false,
        },
        next_of_kin: [],
        selected_nok_index: '',
        consent: false,
        selected_client_id: '',
        case_issue_id: '',
    });

    const toast = useToast();

    const [currentStep, setCurrentStep] = useState(1);
    const [caseId] = useState(PENDING_IDENTIFIER);
    const [trackingId] = useState(PENDING_IDENTIFIER);
    // Keep linked OFW self-filing prefill and payload behavior intact.
    const clientSource = isOwnClientFiler ? 'existing' : 'new';

    // Lazily-loaded PSGC module cache for regionRequiresProvince (see above).
    const addressDataRef = useRef(null);
    // Lazily-loaded address resolver for the confirm modal (see above).
    const [resolveAddress, setResolveAddress] = useState(null);
    useEffect(() => {
        let live = true;
        import('@/data/philippine-addresses').then((mod) => {
            if (live) addressDataRef.current = mod;
        });
        import('@/lib/addressResolver').then((mod) => {
            if (live) setResolveAddress(() => mod.formatResolvedAddress);
        });
        return () => { live = false; };
    }, []);
    // Guards the existing-client prefill below so it fires exactly once,
    // when the late `client` prop arrives — never re-running on edits.
    const clientPrefillRef = useRef(false);
    const [showAddIssue, setShowAddIssue] = useState(false);
    const [newIssueName, setNewIssueName] = useState('');
    const [addingIssue, setAddingIssue] = useState(false);
    const [localIssues, setLocalIssues] = useState(caseIssues);
    const [showCreateModal, setShowCreateModal] = useState(false);
    useEffect(() => { setLocalIssues(caseIssues); }, [caseIssues]);

    async function handleQuickAddIssue() {
        const name = newIssueName.trim();
        if (!name || addingIssue) return;
        setAddingIssue(true);
        try {
            const res = await window.axios.post(route('case-issues.quick'), { name });
            const newIssue = res.data;
            setLocalIssues(prev => [...prev, newIssue]);
            setData('case_issue_id', newIssue.id);
            setNewIssueName('');
            setShowAddIssue(false);
        } catch (err) {
            alert(err.response?.data?.errors?.name?.[0] || 'Failed to add issue.');
        } finally {
            setAddingIssue(false);
        }
    }

    // Own-client prefill: an OFW filer arrives with their linked client.
    useEffect(() => {
        if (!isOwnClientFiler || clientPrefillRef.current) return;
        clientPrefillRef.current = true;
        fillFromClient(client);
        setCurrentStep(2);
    }, [client]);

    // Shared filler for own client + picked existing client.
    function fillFromClient(picked) {
        if (!picked) return;
        setData('selected_client_id', picked.id);
        setData('client', {
            first_name: picked.first_name || '',
            last_name: picked.last_name || '',
            middle_name: picked.middle_name || '',
            suffix: picked.suffix || '',
            date_of_birth: picked.date_of_birth || '',
            sex: normalizeSex(picked.sex) || 'Male',
            email: picked.email || '',
            contact_number: picked.contact_number || '',
        });

        if (picked.addresses?.[0]) {
            const addr = picked.addresses[0];
            setData('address', {
                region: addr.region || '',
                province: addr.province || '',
                city_municipality: addr.city_municipality || '',
                barangay: addr.barangay || '',
                street: addr.street || '',
            });
        }

        if (picked.employments?.[0]) {
            setData('employment', {
                ...buildEmployment(picked.employments[0]),
                employer_name: picked.employments[0].employer_name || '',
                position: picked.employments[0].position || '',
                country: picked.employments[0].country || '',
                start_date: normalizeDateInput(picked.employments[0].start_date),
                end_date: normalizeDateInput(picked.employments[0].end_date),
                last_country: picked.employments[0].last_country || '',
                last_position: picked.employments[0].last_position || '',
                date_of_arrival: normalizeDateInput(picked.employments[0].date_of_arrival),
            });
        }

        if (picked.nextOfKin?.length) {
            setData('next_of_kin', picked.nextOfKin.map((nok) => ({

                id: nok.id,
                first_name: nok.first_name || '',
                middle_name: nok.middle_name || '',
                last_name: nok.last_name || '',
                is_primary: nok.is_primary || false,
                relationship: nok.relationship || '',
                phone_number: nok.phone_number || '',
                email: nok.email || '',
                full_address: nok.full_address || '',
                nok_address: {
                    region: nok.region || '',
                    province: nok.province || '',
                    city_municipality: nok.city_municipality || '',
                    barangay: nok.barangay || '',
                    street: nok.street || '',
                },
            })));
        }

    }

    function handleClientChange(field, value) {
        setData('client', { ...data.client, [field]: value });
        if (errors['client.' + field]) {
            setError('client.' + field, '');
        }
    }

    function handleAddressChange(field, value) {
        // Support both single-field (field, value) and batch ({ field: value, ... }) formats.
        // Batch format is used by AddressDropdowns cascade handlers to avoid React 18 stale
        // closure issue where multiple sequential setData calls wipe each other out.
        if (typeof field === 'object') {
            setData('address', { ...data.address, ...field });
        } else {
            setData('address', { ...data.address, [field]: value });
            if (errors['address.' + field]) {
                setError('address.' + field, '');
            }
        }
    }

    function handleEmploymentChange(field, value) {
        setData('employment', { ...data.employment, [field]: value });
    }

    function updateNokField(idx, field, value) {
        const updated = data.next_of_kin.map((nok, i) =>
            i === idx ? { ...nok, [field]: value } : nok
        );
        setData('next_of_kin', updated);
    }

    function updateNokAddress(idx, address) {
        const updated = data.next_of_kin.map((nok, i) =>
            i === idx ? { ...nok, nok_address: { ...(nok.nok_address || {}), ...address } } : nok
        );
        setData('next_of_kin', updated);
    }

    function handleNext() {
        if (currentStep < 3) {
            if (!validateStep(currentStep)) return;
            clearErrors();
            setCurrentStep((prev) => prev + 1);
        }
    }

    function handleBack() {
        if (currentStep > 1) {
            clearErrors();
            setCurrentStep((prev) => prev - 1);
        }
    }

    function regionRequiresProvince(regionCode) {
        // ponytail: dataset loads async after paint — require province until the
        // lookup resolves (transient; validation only runs on user action).
        const lookup = addressDataRef.current?.getProvincesByRegion;
        if (typeof lookup !== 'function') return true;
        return lookup(regionCode).length > 0;
    }

    function canProceed() {
        if (currentStep === 1) {
            const clientOk = (data.client.first_name.trim().length > 0
                && data.client.last_name.trim().length > 0
                && data.client.date_of_birth.length > 0
                && data.client.sex.length > 0
                && data.client.contact_number.trim().length > 0
                && data.client.email.trim().length > 0);

            const addressOk = (data.address.region.length > 0
                && (!regionRequiresProvince(data.address.region) || data.address.province.length > 0)
                && data.address.city_municipality.length > 0
                && data.address.barangay.length > 0);

            const employmentOk = (data.employment.employer_name.trim().length > 0
                && data.employment.last_country.length > 0
                && data.employment.last_position.trim().length > 0
                && data.employment.date_of_arrival.length > 0);

            // NOK fields required only if NOK entries exist
            const nokOk = data.client_type !== 'NEXT_OF_KIN' || (data.next_of_kin.length === 1 && data.next_of_kin.every(nok =>
                nok.first_name.trim().length > 0
                && nok.last_name.trim().length > 0
                && nok.relationship.length > 0
                && nok.phone_number.trim().length > 0
                && nok.email.trim().length > 0
                && (nok.nok_address?.region || '').length > 0
                && (!regionRequiresProvince(nok.nok_address?.region || '') || (nok.nok_address?.province || '').length > 0)
                && (nok.nok_address?.city_municipality || '').length > 0
                && (nok.nok_address?.barangay || '').length > 0
            ));

            return clientOk && addressOk && employmentOk && nokOk;
        }
        if (currentStep === 2) {
            const baseOk = data.client_type && data.category_ids?.length > 0 && data.case_issue_id && data.vulnerability_indicator;
            if (data.client_type === 'NEXT_OF_KIN') {
                return baseOk && data.selected_nok_index !== '' && !!selectedNok?.email?.trim()
                    && data.nok_vulnerability_indicator;
            }
            return baseOk && data.client.email.trim().length > 0;
        }
        return true;
    }

    /* Legacy helper retained for form validation diagnostics. */
    function getMissingFields() {
        if (currentStep === 1) {
            const missing = [];
            if (!data.client.first_name.trim()) missing.push('First Name');
            if (!data.client.last_name.trim()) missing.push('Last Name');
            if (!data.client.date_of_birth) missing.push('Date of Birth');
            if (!data.client.sex) missing.push('Sex');
            if (!data.client.contact_number.trim()) missing.push('Contact Number');
            if (!data.client.email.trim()) missing.push('OFW Email');
            if (!data.address.region) missing.push('Region');
            if (regionRequiresProvince(data.address.region) && !data.address.province) missing.push('Province');
            if (!data.address.city_municipality) missing.push('City/Municipality');
            if (!data.address.barangay) missing.push('Barangay');
            if (!data.employment.employer_name.trim()) missing.push('Employer Name');
            if (!data.employment.last_country) missing.push('Last Country of Employment');
            if (!data.employment.last_position.trim()) missing.push('Last Occupation');
            if (!data.employment.date_of_arrival) missing.push('Arrival Date');
            if (data.client_type === 'NEXT_OF_KIN') data.next_of_kin.slice(0, 1).forEach((nok, idx) => {
                if (!nok.first_name.trim()) missing.push(`NOK #${idx + 1} First Name`);
                if (!nok.last_name.trim()) missing.push(`NOK #${idx + 1} Last Name`);
                if (!nok.relationship) missing.push(`NOK #${idx + 1} Relationship`);
                if (!nok.phone_number.trim()) missing.push(`NOK #${idx + 1} Phone Number`);
                if (!nok.email.trim()) missing.push(`NOK #${idx + 1} Email`);
                if (!(nok.nok_address?.region || '')) missing.push(`NOK #${idx + 1} Region`);
                if (regionRequiresProvince(nok.nok_address?.region || '') && !(nok.nok_address?.province || '')) missing.push(`NOK #${idx + 1} Province`);
                if (!(nok.nok_address?.city_municipality || '')) missing.push(`NOK #${idx + 1} City/Municipality`);
                if (!(nok.nok_address?.barangay || '')) missing.push(`NOK #${idx + 1} Barangay`);
            });
            return missing;
        }
        if (currentStep === 2) {
            const missing = [];
            if (!data.case_issue_id) missing.push('Case Issue');
            if (!data.vulnerability_indicator) missing.push('Vulnerability Status');
            if (data.client_type === 'NEXT_OF_KIN') {
                if (data.selected_nok_index === '') missing.push('Selected Next of Kin');
                if (!selectedNok?.email?.trim()) missing.push('Selected Next of Kin Email');
                if (!data.nok_vulnerability_indicator) missing.push('NOK Vulnerability Status');
            } else if (!data.client.email.trim()) {
                missing.push('OFW Email');
            }
            return missing;
        }
        return [];
    }

    function validateStep(step) {
        clearErrors();
        let isValid = true;
        const missing = [];

        if (step === 1) {
            if (!data.client.first_name.trim()) {
                setError('client.first_name', 'First name is required.');
                isValid = false;
                missing.push('First Name');
            }
            if (!data.client.last_name.trim()) {
                setError('client.last_name', 'Last name is required.');
                isValid = false;
                missing.push('Last Name');
            }
            if (!data.client.date_of_birth) {
                setError('client.date_of_birth', 'Date of birth is required.');
                isValid = false;
                missing.push('Date of Birth');
            } else if (data.client.date_of_birth > DOB_BOUNDS.max) {
                setError('client.date_of_birth', 'Applicant must be at least 15 years old.');
                isValid = false;
                missing.push('Date of Birth');
            } else if (data.client.date_of_birth < DOB_BOUNDS.min) {
                setError('client.date_of_birth', 'Applicant cannot be over 100 years old.');
                isValid = false;
                missing.push('Date of Birth');
            }
            if (!data.client.sex) {
                setError('client.sex', 'Gender is required.');
                isValid = false;
                missing.push('Sex');
            }
            if (!data.client.contact_number.trim()) {
                setError('client.contact_number', 'Contact number is required.');
                isValid = false;
                missing.push('Contact Number');
            }
            if (!data.client.email.trim()) {
                setError('client.email', 'Email address is required.');
                isValid = false;
                missing.push('OFW Email');
            }
            if (!data.address.region) {
                setError('address.region', 'Region is required.');
                isValid = false;
                missing.push('Region');
            }
            if (regionRequiresProvince(data.address.region) && !data.address.province) {
                setError('address.province', 'Province is required.');
                isValid = false;
                missing.push('Province');
            }
            if (!data.address.city_municipality) {
                setError('address.city_municipality', 'City/Municipality is required.');
                isValid = false;
                missing.push('City/Municipality');
            }
            if (!data.address.barangay) {
                setError('address.barangay', 'Barangay is required.');
                isValid = false;
                missing.push('Barangay');
            }
            if (!data.employment.employer_name.trim()) {
                setError('employment.employer_name', 'Employer name is required.');
                isValid = false;
                missing.push('Employer Name');
            }
            if (!data.employment.last_country) {
                setError('employment.last_country', 'Last country of employment is required.');
                isValid = false;
                missing.push('Last Country of Employment');
            }
            if (!data.employment.last_position.trim()) {
                setError('employment.last_position', 'Last occupation is required.');
                isValid = false;
                missing.push('Last Occupation');
            }
            if (!data.employment.date_of_arrival) {
                setError('employment.date_of_arrival', 'Arrival date is required.');
                isValid = false;
                missing.push('Arrival Date');
            }
            if (!data.employment.start_date) {
                setError('employment.start_date', 'Employment start date is required.');
                isValid = false;
                missing.push('Employment Start Date');
            }
            if (!data.employment.end_date && !data.employment.is_present) {
                setError('employment.end_date', 'Employment end date is required.');
                isValid = false;
                missing.push('Employment End Date');
            }
            if (data.employment.start_date && data.employment.end_date && !data.employment.is_present && data.employment.end_date < data.employment.start_date) {
                setError('employment.end_date', 'End date must be on or after the start date.');
                isValid = false;
                missing.push('Employment End Date (invalid range)');
            }
            const minStartDate = employmentStartFloor(data.client.date_of_birth);
            if (data.employment.start_date && minStartDate && data.employment.start_date < minStartDate) {
                setError('employment.start_date', 'Employment dates are inconsistent with the client profile. Please review and correct them.');
                isValid = false;
                missing.push('Employment Start Date (before working age)');
            }
            if (data.employment.start_date && data.employment.start_date > TODAY) {
                setError('employment.start_date', 'Employment start date cannot be in the future.');
                isValid = false;
                missing.push('Employment Start Date (future)');
            }
            if (data.employment.end_date && !data.employment.is_present && data.employment.end_date > TODAY) {
                setError('employment.end_date', 'Employment end date cannot be in the future.');
                isValid = false;
                missing.push('Employment End Date (future)');
            }

            if (data.client_type === 'NEXT_OF_KIN') data.next_of_kin.slice(0, 1).forEach((nok, idx) => {
                if (!nok.first_name.trim()) {
                    setError(`next_of_kin.${idx}.first_name`, 'First name is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} First Name`);
                }
                if (!nok.last_name.trim()) {
                    setError(`next_of_kin.${idx}.last_name`, 'Last name is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Last Name`);
                }
                if (!nok.relationship) {
                    setError(`next_of_kin.${idx}.relationship`, 'Relationship is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Relationship`);
                }
                if (!nok.phone_number.trim()) {
                    setError(`next_of_kin.${idx}.phone_number`, 'Phone number is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Phone Number`);
                }
                if (!nok.email.trim()) {
                    setError(`next_of_kin.${idx}.email`, 'Email is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Email`);
                }
                if (!(nok.nok_address?.region || '')) {
                    setError(`next_of_kin.${idx}.nok_address.region`, 'Region is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Region`);
                }
                if (regionRequiresProvince(nok.nok_address?.region || '') && !(nok.nok_address?.province || '')) {
                    setError(`next_of_kin.${idx}.nok_address.province`, 'Province is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Province`);
                }
                if (!(nok.nok_address?.city_municipality || '')) {
                    setError(`next_of_kin.${idx}.nok_address.city_municipality`, 'City/Municipality is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} City/Municipality`);
                }
                if (!(nok.nok_address?.barangay || '')) {
                    setError(`next_of_kin.${idx}.nok_address.barangay`, 'Barangay is required.');
                    isValid = false;
                    missing.push(`NOK #${idx + 1} Barangay`);
                }
            });

            if (!isValid) {
                if (missing.length === 1) {
                    toast.error(`${missing[0]} is required.`);
                } else {
                    toast.error(`Complete ${missing.length} required fields: ${missing.join(', ')}`);
                }
            }
        }

        if (step === 2) {
            if (!data.category_ids?.length) {
                setError('category_ids', 'Select at least one category.');
                isValid = false;
                missing.push('Category');
            }
            if (!data.case_issue_id) {
                setError('case_issue_id', 'Case issue is required.');
                isValid = false;
                missing.push('Case Issue');
            }
            if (!data.vulnerability_indicator) {
                setError('vulnerability_indicator', 'Vulnerability status is required.');
                isValid = false;
                missing.push('Vulnerability Status');
            }
            if (data.client_type === 'NEXT_OF_KIN') {
                if (!selectedNok?.email?.trim()) {
                    setError('next_of_kin.email', 'The selected next of kin email is required.');
                    isValid = false;
                    missing.push('Selected Next of Kin Email');
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(selectedNok.email.trim())) {
                    setError('next_of_kin.email', 'Please provide a valid next of kin email address.');
                    isValid = false;
                    missing.push('Valid Next of Kin Email');
                }

                if (!data.nok_vulnerability_indicator) {
                    setError('nok_vulnerability_indicator', 'Next of kin vulnerability status is required.');
                    isValid = false;
                    missing.push('NOK Vulnerability Status');
                }
            } else if (!data.client.email.trim()) {
                setError('client.email', 'The OFW email address is required.');
                isValid = false;
                missing.push('OFW Email');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.client.email.trim())) {
                setError('client.email', 'Please provide a valid email address.');
                isValid = false;
                missing.push('Valid OFW Email');
            }

            if (!isValid) {
                if (missing.length === 1) {
                    toast.error(`${missing[0]} is required.`);
                } else {
                    toast.error(`Complete ${missing.length} required fields: ${missing.join(', ')}`);
                }
            }
        }

        return isValid;
    }

    function canSubmit() {
        return data.client.first_name.trim().length > 0
            && data.client.last_name.trim().length > 0
            && notificationEmail.trim().length > 0
            && data.summary.trim().length > 0
            && data.case_issue_id
            && data.category_ids?.length > 0
            && data.vulnerability_indicator
            && (clientSource === 'existing' || data.consent);
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (processing) return;
        if (currentStep !== 3) return;
        setShowCreateModal(true);
    }

    // Account-first submit: existing pick sends selected_client_id, inline
    // "Add new OFW" sends new_ofw, own record sends nothing extra — the
    // backend falls back to the filer's linked client.
    function handleConfirmSubmit() {
        if (processing) return;
        setShowCreateModal(false);

        // Inertia useForm.post(url, options) sends the form's data — extra
        // fields must ride via transform, not spread into options.
        transform((formData) => {
            const nextOfKin = formData.client_type === 'NEXT_OF_KIN' ? formData.next_of_kin.slice(0, 1).map(nok => ({ ...nok, is_primary: true })) : [];
            const extra = { next_of_kin: nextOfKin, selected_nok_index: formData.client_type === 'NEXT_OF_KIN' ? '0' : '' };
            if (clientSource === 'existing' && formData.selected_client_id) {
                extra.selected_client_id = formData.selected_client_id;
            } else if (clientSource === 'new') {
                extra.new_ofw = {
                    first_name: formData.client.first_name,
                    last_name: formData.client.last_name,
                    middle_name: formData.client.middle_name || null,
                    suffix: formData.client.suffix || null,
                    date_of_birth: formData.client.date_of_birth || null,
                    sex: formData.client.sex || null,
                    email: formData.client.email || null,
                    contact_number: formData.client.contact_number || null,
                    address: formData.address,
                    employment: formData.employment,
                    next_of_kin: nextOfKin,
                };
            }
            return { ...formData, ...extra };
        });

        post(route('cases.store'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Case created successfully.'),
            onError: (formErrors) => {
                const msgs = Object.values(formErrors || {});
                toast.error(msgs[0] || 'Validation failed.');
            },
        });
    }

    function getInitial(name) {
        if (!name) return '?';
        return name.charAt(0).toUpperCase();
    }



    const selectedNokIndex = data.client_type === 'NEXT_OF_KIN' ? 0 : null;
    const selectedNok = selectedNokIndex !== null ? data.next_of_kin[selectedNokIndex] : null;
    const notificationEmail = data.client_type === 'NEXT_OF_KIN'
        ? (selectedNok?.email || '')
        : data.client.email;

    const nokSummary = data.next_of_kin.length > 0
        ? data.next_of_kin.map(n => [n.first_name, n.last_name].filter(Boolean).join(' ')).filter(Boolean).join(', ') || 'Not yet provided'
        : 'No next of kin indicated';

    return (
        <AppLayout title="Create New Case">
            <Head title="Create New Case" />

            {client && (
                <div className="mb-4 rounded-lg bg-primary-fixed border border-outline-variant px-4 py-3 text-sm text-primary">
                    <strong>Pre-filled</strong> from existing client record: {[client.first_name, client.last_name].filter(Boolean).join(' ')}
                </div>
            )}

            <div className="mb-6">
                <>
                    <Link href={route('cases.index')} className="inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary hover:text-primary-container transition-colors">
                        <span className="material-symbols-outlined text-[18px]">arrow_back</span> Back to Cases
                    </Link>
                    <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Create new case</h1>
                            <p className="text-[13px] text-slate-500 mt-1">Register client details, define case parameters and tracking, then document the client's situation.</p>
                        </div>
                        <p className="text-[13px] text-slate-500">Estimated time: 3-5 minutes</p>
                    </div>
                </>
            </div>

            <div data-tour="case-create-steps" className="mb-6 grid grid-cols-3 border-b border-slate-200">
                {STEPS.map((step) => (
                    <div key={step.id} className={`flex items-center justify-center gap-2 border-b-2 py-3 text-[13px] font-semibold ${currentStep === step.id ? 'border-primary text-primary' : currentStep > step.id ? 'border-transparent text-primary' : 'border-transparent text-slate-500'}`}>
                        <span>{step.id}</span><span>{step.title}</span>
                    </div>
                ))}
            </div>

            <form data-tour="case-create-form" onSubmit={handleSubmit} onKeyDown={(e) => {
                // Only intercept Enter on text inputs (not buttons, selects, textareas)
                // This prevents Enter from submitting the form on Steps 1-2
                if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                    e.preventDefault();
                }
            }}>
                <section className="mx-auto max-w-6xl overflow-visible rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="hidden">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">Step Guide</h3>
                            <p className="mt-2 text-[14px] font-bold text-slate-800">Step {currentStep} of {STEPS.length}</p>
                            <p className="mt-1 text-[12px] text-slate-500">Estimated time: 3-5 minutes</p>
                            <div className="mt-4 h-2 w-full rounded-full bg-slate-100">
                                <div className="h-2 rounded-full bg-primary transition-all" style={{ width: `${Math.round((currentStep / STEPS.length) * 100)}%` }} />
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
                                                <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-[13px] font-extrabold transition-colors ${isCompleted ? 'border-primary bg-primary text-white' : isCurrent ? 'border-primary text-primary bg-white' : 'border-outline-variant text-slate-400 bg-white'}`}>
                                                    {isCompleted ? (
                                                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                                                            <polyline points="20 6 9 17 4 12" />
                                                        </svg>
                                                    ) : step.id}
                                                </div>
                                                <div className="pt-1">
                                                    <p className={`text-[14px] font-bold ${isCurrent || isCompleted ? 'text-primary' : 'text-slate-500'}`}>{step.title}</p>
                                                    <p className="text-[12px] text-slate-400 mt-1 leading-snug">{step.description}</p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-5">
                                <h4 className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                                    {currentStep === 1 ? 'Fill client details' : currentStep === 2 ? 'Get oriented' : 'Tell the story'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {currentStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Fill in complete client details.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Add work history and next of kin if applicable.</span></li>
                                        </>
                                    )}
                                    {currentStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>We generate the case number and tracking ID for you.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Choose the right client type.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Indicate any vulnerability status.</span></li>
                                        </>
                                    )}
                                    {currentStep === 3 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Capture the key events and timeline.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-primary shrink-0" /><span>Review summary before final submission.</span></li>
                                        </>
                                    )}
                                </ul>
                                <p className="mt-4 text-[12px] text-slate-500">You can edit details later from the case view.</p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col">
                        <div className="flex-1">
                            <div className="mb-6 rounded-xl border border-slate-200 bg-gradient-to-br from-primary-fixed via-white to-white p-6">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary">Step {currentStep}</p>
                                        <h2 className="text-xl font-bold text-slate-800 mt-2">{STEPS[currentStep - 1].title}</h2>
                                        <p className="text-[13px] text-slate-500 mt-1">{STEPS[currentStep - 1].description}</p>
                                    </div>
                                    <div className="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Progress</p>
                                        <p className="text-[14px] font-bold text-slate-800 mt-1">{Math.round((currentStep / STEPS.length) * 100)}% complete</p>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6">
                                {currentStep === 1 && (
                                    <div className="space-y-6">
                                        <div data-tour="case-create-client-selection" className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Client Type">
                                                <p className="mb-3 text-[13px] text-slate-500">Choose whether this case is for an overseas Filipino worker or their next of kin.</p>
                                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    {[{ value: 'OFW', label: 'Overseas Filipino Worker' }, { value: 'NEXT_OF_KIN', label: 'Next of Kin' }].map((type) => (
                                                        <label key={type.value} className={`flex cursor-pointer items-center gap-3 rounded-lg border p-4 text-[13px] font-semibold transition ${data.client_type === type.value ? 'border-primary bg-primary-fixed text-primary' : 'border-slate-200 text-slate-700 hover:border-primary/50'}`}>
                                                            <input type="radio" name="client-type" value={type.value} checked={data.client_type === type.value} onChange={() => {
                                                                setData((previous) => ({
                                                                    ...previous,
                                                                    client_type: type.value,
                                                                    selected_nok_index: type.value === 'NEXT_OF_KIN' ? '0' : '',
                                                                    next_of_kin: type.value === 'NEXT_OF_KIN'
                                                                        ? [previous.next_of_kin[0] || { first_name: '', middle_name: '', last_name: '', relationship: '', phone_number: '', email: '', is_primary: true, nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } }]
                                                                        : [],
                                                                }));
                                                                clearErrors();
                                                            }} />
                                                            {type.label}
                                                        </label>
                                                    ))}
                                                </div>
                                            </Subsection>
                                        </div>

                                        <>
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Client Information">
                                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                    <Field label="First Name" required>
                                                        <Input value={data.client.first_name} onChange={(e) => handleClientChange('first_name', e.target.value)} required maxLength={255} />
                                                    </Field>
                                                    <Field label="Middle Name">
                                                        <Input value={data.client.middle_name} onChange={(e) => handleClientChange('middle_name', e.target.value)} maxLength={255} />
                                                    </Field>
                                                    <Field label="Last Name" required>
                                                        <Input value={data.client.last_name} onChange={(e) => handleClientChange('last_name', e.target.value)} required maxLength={255} />
                                                    </Field>
                                                    <Field label="Suffix">
                                                        <SelectField value={data.client.suffix} onChange={(e) => handleClientChange('suffix', e.target.value)} options={SUFFIX_OPTIONS.filter(Boolean).map((s) => ({ label: s, value: s }))} placeholder="None" />
                                                    </Field>
                                                    <Field label="Date of Birth" required>
                                                        <Input type="date" value={data.client.date_of_birth} onChange={(e) => handleClientChange('date_of_birth', e.target.value)} min={DOB_BOUNDS.min} max={DOB_BOUNDS.max} required />
                                                    </Field>
                                                    <Field label="Sex" required>
                                                        <SelectField value={data.client.sex} onChange={(e) => handleClientChange('sex', e.target.value)} options={[{ label: 'Male', value: 'Male' }, { label: 'Female', value: 'Female' }]} required />
                                                    </Field>
                                                </div>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                                    <Field label="Contact Number" required>
                                                        <PhoneInput value={data.client.contact_number} onChange={(val) => handleClientChange('contact_number', val)} />
                                                    </Field>
                                                    <Field label="OFW Email Address" required>
                                                        <Input
                                                            type="email"
                                                            value={data.client.email}
                                                            onChange={(e) => handleClientChange('email', e.target.value)}
                                                            onBlur={() => {
                                                                const val = data.client.email.trim();
                                                                if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                                                                    setError('client.email', 'Please provide a valid OFW email address.');
                                                                } else {
                                                                    clearErrors('client.email');
                                                                }
                                                            }}
                                                            placeholder="ofw@email.com"
                                                            required
                                                            maxLength={255}
                                                        />
                                                        <InputError message={errors['client.email']} className="mt-1" />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Address">
                                                <Suspense fallback={<AddressLoadingFallback />}>
                                                <AddressDropdowns
                                                    values={data.address}
                                                    onChange={handleAddressChange}
                                                    errors={{
                                                        region: errors['address.region'],
                                                        province: errors['address.province'],
                                                        city_municipality: errors['address.city_municipality'],
                                                        barangay: errors['address.barangay'],
                                                    }}
                                                />
                                                </Suspense>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Recent Work History">
                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <Field label="Employer Name" required>
                                                        <Input value={data.employment.employer_name} onChange={(e) => handleEmploymentChange('employer_name', e.target.value)} />
                                                    </Field>
                                                    <Field label="Last Country of Employment" required>
                                                        <CountrySelect value={data.employment.last_country} onChange={(v) => handleEmploymentChange('last_country', v)} placeholder="Select country..." />
                                                    </Field>
                                                    <Field label="Last Occupation" required>
                                                        <SearchableSelect value={data.employment.last_position} onChange={(v) => handleEmploymentChange('last_position', v)} options={mergedOccupationOptions} placeholder="Select or type occupation..." allowCustom />
                                                    </Field>
                                                    <Field label="Employment Period" required className="md:col-span-2">
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="date"
                                                                value={data.employment.start_date}
                                                                onChange={(e) => handleEmploymentChange('start_date', e.target.value)}
                                                                min={data.client.date_of_birth ? employmentStartFloor(data.client.date_of_birth) : undefined}
                                                                className="h-10 flex-1 min-w-0 rounded-md border border-outline-variant px-3 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40"
                                                            />
                                                            <span className="text-[11px] font-bold text-slate-400 shrink-0">to</span>
                                                            {data.employment.is_present ? (
                                                                <span className="h-10 flex-1 min-w-0 rounded-md border border-slate-200 bg-slate-50 px-3 flex items-center text-[13px] font-medium text-emerald-700">
                                                                    Present
                                                                </span>
                                                            ) : (
                                                                <input
                                                                    type="date"
                                                                    value={data.employment.end_date}
                                                                    onChange={(e) => handleEmploymentChange('end_date', e.target.value)}
                                                                    min={data.employment.start_date || undefined}
                                                                    className="h-10 flex-1 min-w-0 rounded-md border border-outline-variant px-3 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40"
                                                                />
                                                            )}
                                                        </div>
                                                        <label className="mt-2 inline-flex items-center gap-2 cursor-pointer select-none">
                                                            <input
                                                                type="checkbox"
                                                                checked={!!data.employment.is_present}
                                                                onChange={(e) => {
                                                                    const checked = e.target.checked;
                                                                    setData('employment', {
                                                                        ...data.employment,
                                                                        is_present: checked,
                                                                        end_date: checked ? '' : data.employment.end_date,
                                                                    });
                                                                }}
                                                                className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/40"
                                                            />
                                                            <span className="text-[12px] text-slate-600">Presently employed</span>
                                                        </label>
                                                    </Field>
                                                    <Field label="Arrival Date in Philippines" required>
                                                        <Input type="date" value={data.employment.date_of_arrival} onChange={(e) => handleEmploymentChange('date_of_arrival', e.target.value)} />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        {data.client_type === 'NEXT_OF_KIN' && (
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Next of Kin Information">
                                                <p className="mb-3 text-[13px] text-slate-500">Complete required details for one next of kin contact.</p>
                                                {data.next_of_kin.slice(0, 1).map((nok, idx) => (
                                                    <div key={nok.id ?? `pending-${idx}`} className="mb-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                        <div className="mb-4 flex items-center justify-between">
                                                            <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">
                                                                Next of Kin #{idx + 1}
                                                            </h4>
                                                        </div>

                                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                            <Field label="First Name" required>
                                                                <Input value={nok.first_name} onChange={(e) => updateNokField(idx, 'first_name', e.target.value)} />
                                                            </Field>
                                                            <Field label="Middle Name">
                                                                <Input value={nok.middle_name} onChange={(e) => updateNokField(idx, 'middle_name', e.target.value)} maxLength={255} />
                                                            </Field>
                                                            <Field label="Last Name" required>
                                                                <Input value={nok.last_name} onChange={(e) => updateNokField(idx, 'last_name', e.target.value)} />
                                                            </Field>
                                                            <Field label="Relationship" required>
                                                                <SelectField value={nok.relationship} onChange={(e) => updateNokField(idx, 'relationship', e.target.value)}
                                                                    options={['Mother', 'Father', 'Spouse', 'Sibling', 'Other'].map((r) => ({ label: r, value: r }))}
                                                                    placeholder="Select relationship" />
                                                            </Field>
                                                            <Field label="Phone Number" required className="md:col-span-2">
                                                                <PhoneInput value={nok.phone_number} onChange={(val) => updateNokField(idx, 'phone_number', val)} className="min-w-0" />
                                                            </Field>
                                                            <Field label="Email" required className="md:col-span-2">
                                                                <Input type="email" value={nok.email} onChange={(e) => updateNokField(idx, 'email', e.target.value)} />
                                                            </Field>
                                                        </div>

                                                        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                            <Subsection title="Address">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => updateNokAddress(idx, { ...data.address })}
                                                                    className="mb-3 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-[12px] font-bold text-slate-600 transition hover:border-primary/50 hover:bg-primary-fixed hover:text-primary"
                                                                >
                                                                    <span className="material-symbols-outlined text-[14px]">content_copy</span>
                                                                    Same as client address
                                                                </button>
                                                                <Suspense fallback={<AddressLoadingFallback />}>
                                                                <AddressDropdowns
                                                                    values={nok.nok_address || { region: '', province: '', city_municipality: '', barangay: '', street: '' }}
                                                                    onChange={(field, value) => {
                                                                        if (typeof field === 'object') {
                                                                            updateNokAddress(idx, field);
                                                                        } else {
                                                                            updateNokAddress(idx, { [field]: value });
                                                                        }
                                                                    }}
                                                                />
                                                                </Suspense>
                                                            </Subsection>
                                                        </div>
                                                    </div>
                                                ))}

                                            </Subsection>
                                        </div>

                                        )}

                                        {clientSource === 'new' && (
                                            <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-6 shadow-sm">
                                                <h3 className="text-[12px] font-bold uppercase tracking-wider text-amber-800">Data Privacy Consent</h3>
                                                <p className="mt-2 text-[13px] leading-relaxed text-amber-900/90">
                                                    I confirm that the client has been informed about this system's data privacy terms and conditions, and
                                                    has given consent for their personal data to be collected, processed, and used for case management,
                                                    referral coordination, and service delivery.
                                                </p>
                                                <label className="mt-4 inline-flex items-start gap-3 text-[13px] font-semibold text-amber-900 cursor-pointer">
                                                    <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-700 focus:ring-amber-600" />
                                                    <span>I acknowledge and confirm client consent for data use in the system.</span>
                                                </label>
                                                {!data.consent && <p className="mt-3 text-[12px] font-medium text-amber-800">Required to create a case for a new client.</p>}
                                            </div>
                                        )}
                                    </>
                                    </div>
                                )}

                                {currentStep === 2 && (
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
                                                <Field label="Category" required>
                                                    <CategoryCheckboxDropdown
                                                        categories={categories}
                                                        selectedIds={data.category_ids || []}
                                                        onChange={(ids) => setData('category_ids', ids)}
                                                        error={errors.category_ids}
                                                    />
                                                    <InputError message={errors.category_ids} className="mt-1" />
                                                </Field>
                                            </div>
                                            <div className="mt-5 pt-5 border-t border-slate-200">
                                                {data.client_type === 'NEXT_OF_KIN' ? (
                                                    <Field label="Next of Kin Email Address" required>
                                                        <div className="flex h-10 w-full items-center rounded-md border border-outline-variant bg-slate-50 px-3 text-[13px] text-slate-700">
                                                            {selectedNok?.email || 'Select a next of kin with an email address'}
                                                        </div>
                                                        <InputError message={errors['next_of_kin.email']} className="mt-1" />
                                                    </Field>
                                                ) : (
                                                    <Field label="OFW Email Address" required>
                                                        <div className="flex h-10 w-full items-center rounded-md border border-outline-variant bg-slate-50 px-3 text-[13px] text-slate-700">
                                                            {data.client.email || 'No OFW email address provided'}
                                                        </div>
                                                        <InputError message={errors['client.email']} className="mt-1" />
                                                    </Field>
                                                )}
                                                <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-amber-700">
                                                    <span className="material-symbols-outlined text-[14px]">info</span>
                                                    {data.client_type === 'NEXT_OF_KIN'
                                                        ? 'Case updates will be sent to the selected next of kin because this case is filed for them.'
                                                        : 'Case updates will be sent to the OFW email address.'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">
                                                {data.client_type === 'OFW' ? 'OFW Vulnerability Status' : 'Next of Kin Vulnerability Status'}
                                            </h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Indicate if the client falls under any vulnerable sector.</p>
                                            <div className={`mt-4 grid ${data.client_type === 'OFW' ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2'} gap-4`}>
                                                <Field label={data.client_type === 'OFW' ? 'OFW Vulnerability Status' : 'Client Vulnerability Status'} required>
                                                    <div className="flex flex-wrap gap-3 mt-1">
                                                        {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((opt) => {
                                                            const checked = data.vulnerability_indicator && data.vulnerability_indicator !== 'None' && data.vulnerability_indicator.split(',').map(s => s.trim()).includes(opt);
                                                            return (
                                                                <label key={opt} className="inline-flex items-center gap-2 cursor-pointer">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={checked}
                                                                        onChange={() => {
                                                                            const current = data.vulnerability_indicator && data.vulnerability_indicator !== 'None'
                                                                                ? data.vulnerability_indicator.split(',').map(s => s.trim()).filter(Boolean)
                                                                                : [];
                                                                            const next = checked ? current.filter(v => v !== opt) : [...current, opt];
                                                                            setData('vulnerability_indicator', next.length > 0 ? next.join(', ') : 'None');
                                                                        }}
                                                                        className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/40"
                                                                    />
                                                                    <span className="text-[13px] text-slate-700">{opt}</span>
                                                                </label>
                                                            );
                                                        })}
                                                    </div>
                                                </Field>
                                                {data.client_type === 'NEXT_OF_KIN' && (
                                                    <Field label="Next of Kin Vulnerability Status" required>
                                                        <div className="flex flex-wrap gap-3 mt-1">
                                                            {['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'].map((opt) => {
                                                                const checked = data.nok_vulnerability_indicator && data.nok_vulnerability_indicator !== 'None' && data.nok_vulnerability_indicator.split(',').map(s => s.trim()).includes(opt);
                                                                return (
                                                                    <label key={opt} className="inline-flex items-center gap-2 cursor-pointer">
                                                                        <input
                                                                            type="checkbox"
                                                                            checked={checked}
                                                                            onChange={() => {
                                                                                const current = data.nok_vulnerability_indicator && data.nok_vulnerability_indicator !== 'None'
                                                                                    ? data.nok_vulnerability_indicator.split(',').map(s => s.trim()).filter(Boolean)
                                                                                    : [];
                                                                                const next = checked ? current.filter(v => v !== opt) : [...current, opt];
                                                                                setData('nok_vulnerability_indicator', next.length > 0 ? next.join(', ') : 'None');
                                                                            }}
                                                                            className="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/40"
                                                                        />
                                                                        <span className="text-[13px] text-slate-700">{opt}</span>
                                                                    </label>
                                                                );
                                                            })}
                                                        </div>
                                                    </Field>
                                                )}
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Issues/Concerns</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">
                                                Select the primary issue or concern related to this case.
                                            </p>
                                            <div className="mt-4">
                                                <Field label="Issue/Concern" required>
                                                    <div className="flex gap-2">
                                                        <NativeSelect
                                                            value={data.case_issue_id}
                                                            onChange={(e) => setData('case_issue_id', e.target.value)}
                                                            className="h-10 flex-1 rounded-md border border-outline-variant px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40"
                                                            required
                                                        >
                                                            <option value="">Select issue/concern...</option>
                                                            {localIssues.map((issue) => (
                                                                <option key={issue.id} value={issue.id}>{issue.name}</option>
                                                            ))}
                                                        </NativeSelect>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setNewIssueName(''); setShowAddIssue(!showAddIssue); }}
                                                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-dashed border-primary/50 text-primary transition hover:bg-primary-fixed"
                                                            title="Add new issue"
                                                        >
                                                            <span className="material-symbols-outlined text-[20px]">add</span>
                                                        </button>
                                                    </div>
                                                </Field>
                                            </div>
                                            {showAddIssue && (
                                                <div className="mt-3 rounded-lg border border-outline-variant bg-primary-fixed p-3">
                                                    <Field label="New Issue Name">
                                                        <input
                                                            type="text"
                                                            value={newIssueName}
                                                            onChange={(e) => setNewIssueName(e.target.value)}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                                                            placeholder="Enter new issue name..."
                                                            className="h-10 w-full px-3 py-2 text-[13px]"
                                                            autoFocus
                                                        />
                                                    </Field>
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={handleQuickAddIssue}
                                                            disabled={addingIssue || !newIssueName.trim()}
                                                            className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-[12px] font-semibold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {addingIssue ? 'Adding...' : 'Add'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setShowAddIssue(false); setNewIssueName(''); }}
                                                            className="text-[12px] font-medium text-slate-500 hover:text-slate-700"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {currentStep === 3 && (
                                    <div className="space-y-4">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Narrative</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Use concise plain text to summarize the case background.</p>
                                            <div className="mt-4">
                                                <Field label="Narrative" required>
                                                    <textarea
                                                        rows={8}
                                                        value={data.summary}
                                                        onChange={(e) => setData('summary', e.target.value)}
                                                        placeholder="Describe the client situation and reason for opening the case..."
                                                        className="w-full rounded-md border border-outline-variant px-3 py-3 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40"
                                                        required
                                                    />
                                                </Field>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Case Issues/Concerns</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">
                                                Select the primary issue or concern related to this case.
                                            </p>
                                            <div className="mt-4">
                                                <Field label="Issue/Concern" required>
                                                    <div className="flex gap-2">
                                                        <NativeSelect
                                                            value={data.case_issue_id}
                                                            onChange={(e) => setData('case_issue_id', e.target.value)}
                                                            className="h-10 flex-1 rounded-md border border-outline-variant px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-primary focus:ring-1 focus:ring-primary/40"
                                                            required
                                                        >
                                                            <option value="">Select issue/concern...</option>
                                                            {localIssues.map((issue) => (
                                                                <option key={issue.id} value={issue.id}>{issue.name}</option>
                                                            ))}
                                                        </NativeSelect>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setNewIssueName(''); setShowAddIssue(!showAddIssue); }}
                                                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-dashed border-primary/50 text-primary transition hover:bg-primary-fixed"
                                                            title="Add new issue"
                                                        >
                                                            <span className="material-symbols-outlined text-[20px]">add</span>
                                                        </button>
                                                    </div>
                                                </Field>
                                            </div>
                                            {showAddIssue && (
                                                <div className="mt-3 rounded-lg border border-outline-variant bg-primary-fixed p-3">
                                                    <Field label="New Issue Name">
                                                        <input
                                                            type="text"
                                                            value={newIssueName}
                                                            onChange={(e) => setNewIssueName(e.target.value)}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                                                            placeholder="Enter new issue name..."
                                                            className="h-10 w-full px-3 py-2 text-[13px]"
                                                            autoFocus
                                                        />
                                                    </Field>
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={handleQuickAddIssue}
                                                            disabled={addingIssue || !newIssueName.trim()}
                                                            className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-[12px] font-semibold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                                                        >
                                                            {addingIssue ? 'Adding...' : 'Add'}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setShowAddIssue(false); setNewIssueName(''); }}
                                                            className="text-[12px] font-medium text-slate-500 hover:text-slate-700"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div data-tour="case-create-actions" className="flex items-center justify-between border-t border-slate-200 p-6">
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={handleBack} disabled={currentStep === 1}
                                    className="inline-flex items-center gap-2 rounded-md border border-outline-variant bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                                </button>

                            </div>
                            {currentStep < 3 ? (
                                <div className="flex items-center gap-4">
                                    <span className="text-[13px] text-slate-500">Complete required fields and confirm client consent to continue.</span>
                                    <button type="button" onClick={handleNext} disabled={!canProceed()}
                                        className="inline-flex items-center gap-2 rounded-md bg-primary px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">
                                        Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                    </button>
                                </div>
                            ) : (
                                <button type="button" onClick={handleSubmit} disabled={processing || !canSubmit()}
                                    className="inline-flex items-center gap-2 rounded-md bg-primary px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? 'Creating...' : 'Create Case'}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </form>
            <CaseSummaryModal
                show={showCreateModal}
                data={data}
                caseId={caseId}
                trackingId={trackingId}
                categories={categories}
                caseIssues={caseIssues}
                notificationEmail={notificationEmail}
                resolvedAddress={resolveAddress
                    ? resolveAddress(data.address)
                    : [data.address?.street, data.address?.barangay, data.address?.city_municipality, data.address?.province, data.address?.region].filter(Boolean).join(', ')}
                onClose={() => setShowCreateModal(false)}
                onConfirm={handleConfirmSubmit}
                processing={processing}
                nokSummary={nokSummary}
            />
        </AppLayout>
    );
}
