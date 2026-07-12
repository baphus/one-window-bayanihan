import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useAutoSave from '@/Hooks/useAutoSave';
import useLocalStorageDraft from '@/Hooks/useLocalStorageDraft';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import AddressDropdowns from '@/Components/AddressDropdowns';
import CountrySelect from '@/Components/CountrySelect';
import PhoneInput from '@/Components/PhoneInput';
import ClientProfileSummaryModal from '@/Components/ClientProfileSummaryModal';
import InputError from '@/Components/InputError';
import { useToast } from '@/Hooks/useToast';

const STEPS = [
    { id: 1, title: 'Client Profile', description: 'Enter client information and employment details' },
    { id: 2, title: 'Case Setup', description: 'Define case parameters and tracking' },
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

function Input({ value, onChange, placeholder, type = 'text', maxLength, minLength, readOnly, required, onBlur, className = '' }) {
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
            className={`h-10 w-full rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 ${readOnly ? 'bg-slate-50' : ''} ${className}`}
        />
    );
}

function Select({ value, onChange, options, placeholder, required }) {
    return (
        <select
            value={value}
            onChange={onChange}
            required={required}
            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
        >
            {placeholder && <option value="">{placeholder}</option>}
            {options.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
        </select>
    );
}

function CaseSummaryModal({ show, data, caseId, trackingId, categories, caseIssues, notificationEmail, onClose, onConfirm, processing, isDraft, nokSummary }) {
    if (!show) return null;

    const categoryName = categories.find(c => String(c.id) === String(data.category_id))?.name || '—';
    const issueName = caseIssues.find(i => String(i.id) === String(data.case_issue_id))?.name || '—';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
            <div className="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 className="text-lg font-bold text-slate-900">Confirm Case Creation</h3>
                        <p className="text-[13px] text-slate-500 mt-0.5">Review the details below before creating the case.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-md p-1.5 text-slate-400 hover:text-slate-600 transition-colors">
                        <span className="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div className="max-h-[60vh] overflow-y-auto px-6 py-5 space-y-4">
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
                                {[data.address.barangay, data.address.city_municipality, data.address.province, data.address.region].filter(Boolean).join(', ') || '—'}
                            </span>
                        </div>
                        <div>
                            <span className="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Category</span>
                            <span className="font-semibold text-slate-800">{categoryName}</span>
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

                <div className="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                        <span className="material-symbols-outlined text-[16px]">edit</span>
                        Go Back &amp; Edit
                    </button>
                    <button
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span className="material-symbols-outlined text-[16px]">check_circle</span>
                        {processing ? (isDraft ? 'Publishing...' : 'Creating...') : 'Confirm &amp; Create Case'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function CaseCreate() {
    const { client, categories = [], existingDraft, auth, caseIssues = [] } = usePage().props;

    function normalizeSex(value) {
        if (!value) return '';
        const upper = value.toUpperCase();
        if (upper === 'MALE') return 'Male';
        if (upper === 'FEMALE') return 'Female';
        return value;
    }

    const { data, setData, post, put, processing, errors, setError, clearErrors } = useForm({
        client_type: 'OFW',
        category_id: '',
        vulnerability_indicator: 'None',
        nok_vulnerability_indicator: 'None',
        summary: '',
        client: {
            first_name: '',
            last_name: '',
            middle_initial: '',
            suffix: '',
            date_of_birth: '',
            sex: 'Male',
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
            is_present: false,
        },
        next_of_kin: [],
        selected_nok_index: '',
        consent: false,
        selected_client_id: '',
        is_draft: false,
        case_issue_id: '',
    });

    const toast = useToast();

    const [currentStep, setCurrentStep] = useState(1);
    const [caseId, setCaseId] = useState(() => GenerateCaseId());
    const [trackingId, setTrackingId] = useState(() => GenerateTrackingId());
    const [clientSource, setClientSource] = useState('new');
    const [selectedClient, setSelectedClient] = useState(null);
    const [viewMode, setViewMode] = useState('grid');
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const searchDebounceRef = useRef(null);
    const draftIdRef = useRef(existingDraft?.id || null);
    const restoredRef = useRef(false);
    const [searchResults, setSearchResults] = useState([]);
    const [searchLoading, setSearchLoading] = useState(false);
    const [showAddIssue, setShowAddIssue] = useState(false);
    const [newIssueName, setNewIssueName] = useState('');
    const [addingIssue, setAddingIssue] = useState(false);
    const [localIssues, setLocalIssues] = useState(caseIssues);
    const [showCreateModal, setShowCreateModal] = useState(false);
    useEffect(() => { setLocalIssues(caseIssues); }, [caseIssues]);

    const initialFormRef = useRef({
        formData: {
            client_type: 'OFW',
            category_id: '',
            vulnerability_indicator: 'None',
            nok_vulnerability_indicator: 'None',
            summary: '',
            client: { first_name: '', last_name: '', middle_initial: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' },
            address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
            employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '', is_present: false },
            next_of_kin: [],
            selected_nok_index: '',
            consent: false,
            is_draft: false,
            case_issue_id: '',
        },
        clientSource: 'new',
    });

    function formDataEqual(a, b) {
        return a.client_type === b.client_type
            && a.category_id === b.category_id
            && a.vulnerability_indicator === b.vulnerability_indicator
            && a.nok_vulnerability_indicator === b.nok_vulnerability_indicator
            && a.summary === b.summary
            && a.client.first_name === b.client.first_name
            && a.client.last_name === b.client.last_name
            && a.client.middle_initial === b.client.middle_initial
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
            && JSON.stringify(a.next_of_kin) === JSON.stringify(b.next_of_kin)
            && a.selected_nok_index === b.selected_nok_index
            && a.consent === b.consent
            && a.is_draft === b.is_draft
            && a.case_issue_id === b.case_issue_id;
    }

    const hasDirty = useMemo(() => {
        return !formDataEqual(data, initialFormRef.current.formData)
            || clientSource !== initialFormRef.current.clientSource;
    }, [data, clientSource]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);

    const { autoSaveStatus, draftId: autoSaveDraftId, cancelPendingSave } = useAutoSave({
        formData: data,
        draftId: existingDraft?.id || null,
    });

    const { hasLocalBackup, localBackup, clearLocalBackup } = useLocalStorageDraft({
        formData: data,
        userId: auth.user?.id,
        enabled: !existingDraft && clientSource === 'new',
    });

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

    useEffect(() => {
        if (!hasLocalBackup || existingDraft || !localBackup?.data || restoredRef.current) return;
        restoredRef.current = true;

        const backupData = localBackup.data;

        if (backupData.client) setData('client', { ...backupData.client });
        if (backupData.address) setData('address', { ...backupData.address });
        if (backupData.employment) setData('employment', { ...backupData.employment });
        if (backupData.next_of_kin) {
            const nok = Array.isArray(backupData.next_of_kin) ? backupData.next_of_kin : [backupData.next_of_kin];
            setData('next_of_kin', [...nok]);
        }
        setData('client_type', backupData.client_type || 'OFW');
        setData('category_id', backupData.category_id || '');
        setData('selected_nok_index', backupData.selected_nok_index ?? '');
        setData('vulnerability_indicator', backupData.vulnerability_indicator || 'None');
        setData('nok_vulnerability_indicator', backupData.nok_vulnerability_indicator || 'None');
        setData('summary', backupData.summary || '');
        setData('consent', backupData.consent ?? false);
        if (backupData.case_issue_id !== undefined) setData('case_issue_id', backupData.case_issue_id);

        initialFormRef.current = {
            formData: backupData,
            clientSource: 'new',
        };
    }, [hasLocalBackup, existingDraft, localBackup, setData]);

    useEffect(() => {
        if (autoSaveDraftId) draftIdRef.current = autoSaveDraftId;
    }, [autoSaveDraftId]);

    // Fetch clients from API — loads recent clients on mount and filters on search
    useEffect(() => {
        let cancelled = false;
        setSearchLoading(true);
        const params = {};
        const q = debouncedSearch.trim();
        if (q) params.q = q;
        window.axios.get('/api/clients', { params })
            .then((res) => {
                if (!cancelled) {
                    const mapped = (res.data.data || []).map((c) => ({
                        ...c,
                        full_name: [c.first_name, c.middle_initial, c.last_name, c.suffix].filter(Boolean).join(' '),
                        has_case: c.case_files_count > 0,
                        case_count: c.case_files_count || 0,
                    }));
                    setSearchResults(mapped);
                    setSearchLoading(false);
                }
            })
            .catch(() => {
                if (!cancelled) setSearchLoading(false);
            });
        return () => { cancelled = true; };
    }, [debouncedSearch]);

    useEffect(() => {
        if (client) {
            setClientSource('existing');
            setData('selected_client_id', client.id);
            setData('client', {
                ...data.client,
                first_name: client.first_name || '',
                last_name: client.last_name || '',
                middle_initial: client.middle_initial || '',
                suffix: client.suffix || '',
                date_of_birth: client.date_of_birth || '',
                sex: normalizeSex(client.sex) || 'Male',
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
            }

            if (client.nextOfKin?.length) {
                setData('next_of_kin', client.nextOfKin.map(nok => ({
                    id: nok.id,
                    first_name: nok.first_name || '',
                    middle_initial: nok.middle_initial || '',
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

            // Update initial ref so dirty tracking starts from pre-filled state
            initialFormRef.current = {
        formData: {
            client_type: 'OFW',
            category_id: '',
            vulnerability_indicator: 'None',
            nok_vulnerability_indicator: 'None',
            summary: '',
                    client: {
                        first_name: client.first_name || '',
                        last_name: client.last_name || '',
                        middle_initial: client.middle_initial || '',
                        suffix: client.suffix || '',
                        date_of_birth: client.date_of_birth || '',
                        sex: normalizeSex(client.sex) || 'Male',
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
                    next_of_kin: client.nextOfKin?.length
                        ? client.nextOfKin.map(nok => ({
                            id: nok.id,
                            first_name: nok.first_name || '',
                            middle_initial: nok.middle_initial || '',
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
                        }))
                        : [],
                    selected_nok_index: '',
                    consent: false,
                    is_draft: false,
                    case_issue_id: '',
                },
                clientSource: 'existing',
            };

            setSelectedClient(null);
        }
    }, []);

    // Seed form state when an existing draft is loaded
    useEffect(() => {
        if (!existingDraft) return;

        // 1. Case-level fields
        setData('client_type', existingDraft.client_type || 'OFW');
        setData('category_id', existingDraft.category_id || '');
        setData('selected_nok_index', existingDraft.selected_nok_index ?? '');
        setData('vulnerability_indicator', existingDraft.vulnerability_indicator || 'None');
        setData('summary', existingDraft.summary || '');
        setData('is_draft', true);
        if (existingDraft.case_issue_id) setData('case_issue_id', existingDraft.case_issue_id);

        let clientData = null;
        let src = 'new';
        let selId = '';

        // 2. Client data sourcing
        if (existingDraft.client) {
            // Linked Client model
            clientData = existingDraft.client;
            src = 'existing';
            selId = existingDraft.client.id;

            setData('client', {
                ...data.client,
                first_name: existingDraft.client.first_name || '',
                last_name: existingDraft.client.last_name || '',
                middle_initial: existingDraft.client.middle_initial || '',
                suffix: existingDraft.client.suffix || '',
                date_of_birth: existingDraft.client.date_of_birth || '',
                sex: normalizeSex(existingDraft.client.sex) || 'Male',
                email: existingDraft.client.email || '',
                contact_number: existingDraft.client.contact_number || '',
            });

            if (existingDraft.client.addresses?.[0]) {
                setData('address', {
                    ...data.address,
                    region: existingDraft.client.addresses[0].region || '',
                    province: existingDraft.client.addresses[0].province || '',
                    city_municipality: existingDraft.client.addresses[0].city_municipality || '',
                    barangay: existingDraft.client.addresses[0].barangay || '',
                    street: existingDraft.client.addresses[0].street || '',
                });
            }

            if (existingDraft.client.employments?.[0]) {
                setData('employment', {
                    ...data.employment,
                    employer_name: existingDraft.client.employments[0].employer_name || '',
                    position: existingDraft.client.employments[0].position || '',
                    country: existingDraft.client.employments[0].country || '',
                    last_country: existingDraft.client.employments[0].last_country || '',
                    last_position: existingDraft.client.employments[0].last_position || '',
                    date_of_arrival: existingDraft.client.employments[0].date_of_arrival || '',
                });
            }

            if (existingDraft.client.nextOfKin?.length) {
                setData('next_of_kin', existingDraft.client.nextOfKin.map(nok => ({
                    id: nok.id,
                    first_name: nok.first_name || '',
                    middle_initial: nok.middle_initial || '',
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
        } else if (existingDraft.draft_client_data) {
            // JSON blob from drafts. Older existing-client drafts may have
            // selected_client_id here even when case.client_id was not persisted.
            try {
                clientData = typeof existingDraft.draft_client_data === 'string'
                    ? JSON.parse(existingDraft.draft_client_data)
                    : existingDraft.draft_client_data;
            } catch {
                clientData = null;
            }

            if (clientData) {
                if (clientData.selected_client_id) {
                    src = 'existing';
                    selId = clientData.selected_client_id;
                }

                setData('client', {
                    ...data.client,
                    first_name: clientData.first_name || '',
                    last_name: clientData.last_name || '',
                    middle_initial: clientData.middle_initial || '',
                    suffix: clientData.suffix || '',
                    date_of_birth: clientData.date_of_birth || '',
                    sex: normalizeSex(clientData.sex) || 'Male',
                    email: clientData.email || '',
                    contact_number: clientData.contact_number || '',
                });

                if (clientData.address) {
                    setData('address', {
                        ...data.address,
                        region: clientData.address.region || '',
                        province: clientData.address.province || '',
                        city_municipality: clientData.address.city_municipality || '',
                        barangay: clientData.address.barangay || '',
                        street: clientData.address.street || '',
                    });
                }

                if (clientData.employment) {
                    setData('employment', {
                        ...data.employment,
                        employer_name: clientData.employment.employer_name || '',
                        position: clientData.employment.position || '',
                        country: clientData.employment.country || '',
                        last_country: clientData.employment.last_country || '',
                        last_position: clientData.employment.last_position || '',
                        date_of_arrival: clientData.employment.date_of_arrival || '',
                    });
                }

                if (clientData.next_of_kin) {
                    const raw = clientData.next_of_kin;
                    const nokArray = Array.isArray(raw) ? raw : (raw.first_name ? [raw] : []);
                    setData('next_of_kin', nokArray.map((nok, idx) => ({
                        id: nok.id || null,
                        first_name: nok.first_name || '',
                        middle_initial: nok.middle_initial || '',
                        last_name: nok.last_name || '',
                        is_primary: nok.is_primary ?? (idx === 0),
                        relationship: nok.relationship || '',
                        phone_number: nok.phone_number || '',
                        email: nok.email || '',
                        full_address: nok.full_address || '',
                        nok_address: {
                            region: nok.region || nok.nok_address?.region || '',
                            province: nok.province || nok.nok_address?.province || '',
                            city_municipality: nok.city_municipality || nok.nok_address?.city_municipality || '',
                            barangay: nok.barangay || nok.nok_address?.barangay || '',
                            street: nok.street || nok.nok_address?.street || '',
                        },
                    })));
                }
            }
        }

        // 3. Client source and selection
        const c = clientData || {};
        const emps = c.employments || [];
        const noks = c.nextOfKin || [];

        setClientSource(src);
        setData('selected_client_id', selId);
        setData('consent', false);
        setSelectedClient(null);

        // 4. When editing a draft with a linked client, skip the client selection
        //    search screen and go directly to case setup (step 2).
        if (src === 'existing' && selId) {
            setCurrentStep(2);
        }

        // 5. Generated IDs — override with existing draft's real IDs
        setCaseId(existingDraft.case_number);
        setTrackingId(existingDraft.tracker_number);

        // 5. Dirty tracking reset — follow handleConfirmClient pattern
        initialFormRef.current = {
            formData: {
                client_type: existingDraft.client_type || 'OFW',
                category_id: existingDraft.category_id || '',
                vulnerability_indicator: existingDraft.vulnerability_indicator || 'None',
                nok_vulnerability_indicator: existingDraft.nok_vulnerability_indicator || 'None',
                summary: existingDraft.summary || '',
                client: {
                    first_name: c.first_name || '',
                    last_name: c.last_name || '',
                    middle_initial: c.middle_initial || '',
                    suffix: c.suffix || '',
                    date_of_birth: c.date_of_birth || '',
                    sex: normalizeSex(c.sex) || 'Male',
                    email: c.email || '',
                    contact_number: c.contact_number || '',
                },
                address: {
                    region: (c.addresses?.[0]?.region || c.address?.region) || '',
                    province: (c.addresses?.[0]?.province || c.address?.province) || '',
                    city_municipality: (c.addresses?.[0]?.city_municipality || c.address?.city_municipality) || '',
                    barangay: (c.addresses?.[0]?.barangay || c.address?.barangay) || '',
                    street: (c.addresses?.[0]?.street || c.address?.street) || '',
                },
                employment: {
                    employer_name: (emps[0]?.employer_name || c.employment?.employer_name) || '',
                    position: (emps[0]?.position || c.employment?.position) || '',
                    country: (emps[0]?.country || c.employment?.country) || '',
                    start_date: '',
                    end_date: '',
                    last_country: (emps[0]?.last_country || c.employment?.last_country) || '',
                    last_position: (emps[0]?.last_position || c.employment?.last_position) || '',
                    date_of_arrival: (emps[0]?.date_of_arrival || c.employment?.date_of_arrival) || '',
                },
                next_of_kin: (() => {
                    const rawNoks = noks.length ? noks : (c.next_of_kin ? (Array.isArray(c.next_of_kin) ? c.next_of_kin : [c.next_of_kin]) : []);
                    return rawNoks.map(nok => ({
                        id: nok.id || null,
                        first_name: nok.first_name || '',
                        middle_initial: nok.middle_initial || '',
                        last_name: nok.last_name || '',
                        is_primary: nok.is_primary || false,
                        relationship: nok.relationship || '',
                        phone_number: nok.phone_number || '',
                        email: nok.email || '',
                        full_address: nok.full_address || '',
                        nok_address: {
                            region: nok.region || nok.nok_address?.region || '',
                            province: nok.province || nok.nok_address?.province || '',
                            city_municipality: nok.city_municipality || nok.nok_address?.city_municipality || '',
                            barangay: nok.barangay || nok.nok_address?.barangay || '',
                            street: nok.street || nok.nok_address?.street || '',
                        },
                    }));
                })(),
                selected_nok_index: '',
                consent: false,
                is_draft: true,
                case_issue_id: existingDraft.case_issue_id || '',
            },
            clientSource: src,
        };
    }, []);

    const stepProgress = Math.round((currentStep / STEPS.length) * 100);

    function handleClientChange(field, value) {
        setData('client', { ...data.client, [field]: value });
        if (errors['client.' + field]) {
            setError('client.' + field, '');
        }
    }

    async function handleClientSelect(client) {
        if (!client?.id) return;
        setSelectedClient(null);
        try {
            const res = await window.axios.get(`/api/clients/${client.id}`);
            const data = res.data.data;
            const mapped = {
                ...data,
                full_name: [data.first_name, data.middle_initial, data.last_name, data.suffix].filter(Boolean).join(' '),
                has_case: !!data.case_file,
                case_count: data.case_file ? 1 : 0,
            };
            setSelectedClient(mapped);
        } catch {
            toast.error('Failed to load client details.');
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

    function addNok() {
        setData('next_of_kin', [
            ...data.next_of_kin,
            {
                id: null,
                first_name: '',
                middle_initial: '',
                last_name: '',
                is_primary: data.next_of_kin.length === 0,
                relationship: '',
                phone_number: '',
                email: '',
                full_address: '',
                nok_address: {
                    region: '', province: '', city_municipality: '', barangay: '', street: '',
                },
            },
        ]);
    }

    function removeNok(idx) {
        const updated = data.next_of_kin.filter((_, i) => i !== idx);
        if (data.next_of_kin[idx]?.is_primary && updated.length > 0) {
            updated[0].is_primary = true;
        }
        setData('next_of_kin', updated);
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

    function setPrimaryNok(idx) {
        const updated = data.next_of_kin.map((n, i) => ({
            ...n, is_primary: i === idx
        }));
        setData('next_of_kin', updated);
    }

    function handleNext() {
        if (currentStep < 3) {
            cancelPendingSave();
            if (!validateStep(currentStep)) return;
            clearErrors();
            setCurrentStep((prev) => prev + 1);
        }
    }

    function handleBack() {
        if (currentStep > 1) {
            cancelPendingSave();
            clearErrors();
            setCurrentStep((prev) => prev - 1);
        }
    }

    function canProceed() {
        if (currentStep === 1) {
            const clientOk = data.client.first_name.trim().length > 0
                && data.client.last_name.trim().length > 0
                && data.client.date_of_birth.length > 0
                && data.client.sex.length > 0
                && data.client.contact_number.trim().length > 0
                && data.client.email.trim().length > 0;

            const addressOk = data.address.region.length > 0
                && data.address.province.length > 0
                && data.address.city_municipality.length > 0
                && data.address.barangay.length > 0;

            const employmentOk = data.employment.employer_name.trim().length > 0
                && data.employment.last_country.length > 0
                && data.employment.last_position.trim().length > 0
                && data.employment.date_of_arrival.length > 0;

            // NOK fields required only if NOK entries exist
            const nokOk = data.next_of_kin.length === 0 || data.next_of_kin.every(nok =>
                nok.first_name.trim().length > 0
                && nok.last_name.trim().length > 0
                && nok.relationship.length > 0
                && nok.phone_number.trim().length > 0
                && nok.email.trim().length > 0
                && (nok.nok_address?.region || '').length > 0
                && (nok.nok_address?.province || '').length > 0
                && (nok.nok_address?.city_municipality || '').length > 0
                && (nok.nok_address?.barangay || '').length > 0
            );

            return clientOk && addressOk && employmentOk && nokOk;
        }
        if (currentStep === 2) {
            const baseOk = data.client_type && data.category_id && data.case_issue_id && data.vulnerability_indicator;
            if (data.client_type === 'NEXT_OF_KIN') {
                return baseOk && data.selected_nok_index !== '' && !!selectedNok?.email?.trim()
                    && data.nok_vulnerability_indicator;
            }
            return baseOk && data.client.email.trim().length > 0;
        }
        return true;
    }

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
            if (!data.address.province) missing.push('Province');
            if (!data.address.city_municipality) missing.push('City/Municipality');
            if (!data.address.barangay) missing.push('Barangay');
            if (!data.employment.employer_name.trim()) missing.push('Employer Name');
            if (!data.employment.last_country) missing.push('Last Country of Employment');
            if (!data.employment.last_position.trim()) missing.push('Last Job Position');
            if (!data.employment.date_of_arrival) missing.push('Arrival Date');
            data.next_of_kin.forEach((nok, idx) => {
                if (!nok.first_name.trim()) missing.push(`NOK #${idx + 1} First Name`);
                if (!nok.last_name.trim()) missing.push(`NOK #${idx + 1} Last Name`);
                if (!nok.relationship) missing.push(`NOK #${idx + 1} Relationship`);
                if (!nok.phone_number.trim()) missing.push(`NOK #${idx + 1} Phone Number`);
                if (!nok.email.trim()) missing.push(`NOK #${idx + 1} Email`);
                if (!(nok.nok_address?.region || '')) missing.push(`NOK #${idx + 1} Region`);
                if (!(nok.nok_address?.province || '')) missing.push(`NOK #${idx + 1} Province`);
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
            if (!data.address.province) {
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
                setError('employment.last_position', 'Last job position is required.');
                isValid = false;
                missing.push('Last Job Position');
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

            data.next_of_kin.forEach((nok, idx) => {
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
                if (!(nok.nok_address?.province || '')) {
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
            if (!data.category_id) {
                setError('category_id', 'Category is required.');
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
                if (data.selected_nok_index === '') {
                    setError('selected_nok_index', 'Please select the next of kin receiving case notifications.');
                    isValid = false;
                    missing.push('Selected Next of Kin');
                }

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
            && data.category_id
            && data.vulnerability_indicator
            && (clientSource === 'existing' || data.consent);
    }

    function handleSaveDraft(e) {
        e.preventDefault();
        bypassNext();

        const submitData = {
            ...data,
            is_draft: true,
        };

        if (existingDraft) {
            put(route('cases.save-draft', existingDraft.id), {
                data: submitData,
                onSuccess: () => { },
                onError: (errors) => {
                    const msgs = Object.values(errors);
                    toast.error(msgs[0] || 'Validation failed.');
                },
                preserveState: false,
                preserveScroll: true,
            });
        } else {
            post(route('cases.store'), {
                data: submitData,
                onSuccess: () => { },
                onError: (errors) => {
                    const msgs = Object.values(errors);
                    toast.error(msgs[0] || 'Validation failed.');
                },
                preserveState: false,
                preserveScroll: true,
            });
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        if (currentStep !== 3) return;
        setShowCreateModal(true);
    }

    function handleConfirmSubmit() {
        setShowCreateModal(false);
        bypassNext();

        if (existingDraft) {
            // Publishing does NOT send form data — publishes the draft as last saved.
            // User should save via "Update Draft" first.
            post(route('cases.publish', existingDraft.id), {
                onSuccess: () => { clearLocalBackup(); },
                onError: (errors) => {
                    const msgs = Object.values(errors);
                    toast.error(msgs[0] || 'Validation failed.');
                },
                preserveScroll: true,
            });
            return;
        }

        post(route('cases.store'), {
            data: { ...data, is_draft: false },
            onSuccess: () => { clearLocalBackup(); },
            onError: (errors) => {
                const msgs = Object.values(errors);
                toast.error(msgs[0] || 'Validation failed.');
            },
            preserveScroll: true,
        });
    }

    function getInitial(name) {
        if (!name) return '?';
        return name.charAt(0).toUpperCase();
    }

    function handleSwitchToNew() {
        setClientSource('new');
        setData('selected_client_id', '');
        setData('client', { first_name: '', last_name: '', middle_initial: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' });
        setData('address', { region: '', province: '', city_municipality: '', barangay: '', street: '' });
        setData('employment', { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '', is_present: false });
        setData('next_of_kin', []);
        setData('consent', false);
        setSelectedClient(null);
        setSearchQuery('');
        setDebouncedSearch('');
        initialFormRef.current = {
                formData: {
                    client_type: 'OFW',
                    category_id: '',
                    vulnerability_indicator: 'None',
                    nok_vulnerability_indicator: 'None',
                    summary: '',
                client: { first_name: '', last_name: '', middle_initial: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' },
                address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
                employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '', is_present: false },
                next_of_kin: [],
                selected_nok_index: '',
                consent: false,
                is_draft: false,
                case_issue_id: '',
            },
            clientSource: 'new',
        };
    }

    function handleSwitchToExisting() {
        setClientSource('existing');
        clearLocalBackup();
    }

function handleConfirmClient(client) {
    if (!client) return;

    setClientSource('existing');
    setData('selected_client_id', client.id);
    setData('client', {
        ...data.client,
        first_name: client.first_name || '',
        last_name: client.last_name || '',
        middle_initial: client.middle_initial || '',
        suffix: client.suffix || '',
        date_of_birth: client.date_of_birth || '',
        sex: normalizeSex(client.sex) || 'Male',
        email: client.email || '',
        contact_number: client.contact_number || '',
    });

    if (client.addresses?.[0]) {
        const addr = client.addresses[0];
        setData('address', {
            ...data.address,
            region: addr.region || '',
            province: addr.province || '',
            city_municipality: addr.city_municipality || '',
            barangay: addr.barangay || '',
            street: addr.street || '',
        });
    }

    if (client.employments?.[0]) {
        const emp = client.employments[0];
        setData('employment', {
            ...data.employment,
            employer_name: emp.employer_name || '',
            position: emp.position || '',
            country: emp.country || '',
            last_country: emp.last_country || '',
            last_position: emp.last_position || '',
            date_of_arrival: emp.date_of_arrival || '',
        });
    }

    if (client.nextOfKin?.length) {
        setData('next_of_kin', client.nextOfKin.map(nok => ({
            id: nok.id,
            first_name: nok.first_name || '',
            middle_initial: nok.middle_initial || '',
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

    // CRITICAL: Update initialFormRef so dirty tracking starts from pre-filled state
        initialFormRef.current = {
            formData: {
                client_type: 'OFW',
                category_id: '',
                vulnerability_indicator: 'None',
                nok_vulnerability_indicator: 'None',
                summary: '',
            client: {
                first_name: client.first_name || '',
                last_name: client.last_name || '',
                middle_initial: client.middle_initial || '',
                suffix: client.suffix || '',
                date_of_birth: client.date_of_birth || '',
                sex: normalizeSex(client.sex) || 'Male',
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
                start_date: '',
                end_date: '',
                last_country: client.employments?.[0]?.last_country || '',
                last_position: client.employments?.[0]?.last_position || '',
                date_of_arrival: client.employments?.[0]?.date_of_arrival || '',
            },
            next_of_kin: client.nextOfKin?.length
                ? client.nextOfKin.map(nok => ({
                    id: nok.id,
                    first_name: nok.first_name || '',
                    middle_initial: nok.middle_initial || '',
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
                }))
                : [],
            selected_nok_index: '',
            consent: false,
            is_draft: false,
            case_issue_id: '',
        },
        clientSource: 'existing',
    };

    setSelectedClient(null);
}

    const selectedNokIndex = data.selected_nok_index !== '' ? Number(data.selected_nok_index) : null;
    const selectedNok = selectedNokIndex !== null ? data.next_of_kin[selectedNokIndex] : null;
    const notificationEmail = data.client_type === 'NEXT_OF_KIN'
        ? (selectedNok?.email || '')
        : data.client.email;

    const nokSummary = data.next_of_kin.length > 0
        ? data.next_of_kin.map(n => [n.first_name, n.last_name].filter(Boolean).join(' ')).filter(Boolean).join(', ') || 'Not yet provided'
        : 'No next of kin indicated';

    return (
        <AppLayout title={existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'}>
            <Head title={existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'} />

            {client && (
                <div className="mb-4 rounded-lg bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-700">
                    <strong>Pre-filled</strong> from existing client record: {[client.first_name, client.last_name].filter(Boolean).join(' ')}
                </div>
            )}

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">{existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'}</h1>
                        <p className="text-sm text-slate-500 mt-1">{existingDraft ? 'Continue editing your draft case before submitting or publishing.' : 'A guided onboarding flow to register the case with confidence.'}</p>
                    </div>
                    <Link href={existingDraft ? route('cases.drafts') : route('cases.index')} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0">&larr; {existingDraft ? 'Back to Drafts' : 'Back to Cases'}</Link>
                </div>
            </div>

            <form data-tour="case-create-form" onSubmit={handleSubmit} onKeyDown={(e) => {
                // Only intercept Enter on text inputs (not buttons, selects, textareas)
                // This prevents Enter from submitting the form on Steps 1-2
                if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                    e.preventDefault();
                }
            }}>
                <section className="mx-auto flex max-w-6xl overflow-visible rounded-xl border border-slate-300 bg-white shadow-sm">
                    <div data-tour="case-create-steps" className="w-1/3 min-w-[280px] max-w-[320px] shrink-0 border-r border-slate-300 bg-slate-50/60 p-8">
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
                                                <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-[13px] font-extrabold transition-colors ${isCompleted ? 'border-indigo-600 bg-indigo-600 text-white' : isCurrent ? 'border-indigo-600 text-indigo-600 bg-white' : 'border-slate-300 text-slate-400 bg-white'}`}>
                                                    {isCompleted ? (
                                                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
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
                                    {currentStep === 1 ? 'Fill client details' : currentStep === 2 ? 'Get oriented' : 'Tell the story'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {currentStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Fill in complete client details.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Add work history and next of kin if applicable.</span></li>
                                        </>
                                    )}
                                    {currentStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>We generate the case number and tracking ID for you.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Choose the right client type.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Indicate any vulnerability status.</span></li>
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
                                    <div className="space-y-6">
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Client Selection">
                                                <p className="mb-3 text-[13px] text-slate-500">
                                                    Choose an existing client record to pre-fill information, or create a new client.
                                                </p>
                                                <div className="mb-6">
                                                    <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${clientSource === 'existing' ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="client-source" className="sr-only" checked={clientSource === 'existing'} onChange={handleSwitchToExisting} /> Existing Clients
                                                        </label>
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${clientSource === 'new' ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="client-source" className="sr-only" checked={clientSource === 'new'} onChange={handleSwitchToNew} /> New Client
                                                        </label>
                                                    </div>
                                                </div>

                                                {clientSource === 'existing' && (
                                                    <div>
                                                        <div className="mb-4">
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
                                                                placeholder="Search clients by name..."
                                                                className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />
                                                        </div>

                                                        <div className="mb-4 flex items-center justify-between">
                                                            <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">
                                                                {debouncedSearch.trim()
                                                                    ? `${searchLoading ? 'Searching...' : `${searchResults.length} client(s) found`}`
                                                                    : searchResults.length > 0
                                                                        ? `Recent clients (${searchResults.length})`
                                                                        : 'Type to search clients'}
                                                            </span>
                                                            <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setViewMode('grid')}
                                                                    className={`flex items-center justify-center rounded-md px-3 py-1 text-[12px] font-bold transition-all ${viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}
                                                                >
                                                                    <span className="material-symbols-outlined text-[16px]">grid_view</span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => setViewMode('list')}
                                                                    className={`flex items-center justify-center rounded-md px-3 py-1 text-[12px] font-bold transition-all ${viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}
                                                                >
                                                                    <span className="material-symbols-outlined text-[16px]">list</span>
                                                                </button>
                                                            </div>
                                                        </div>

                                                        {searchLoading && searchResults.length === 0 ? (
                                                            <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                <span className="material-symbols-outlined text-[40px] mb-3 animate-pulse">search</span>
                                                                <p className="text-[14px] font-medium text-slate-500">Loading clients...</p>
                                                            </div>
                                                        ) : searchResults.length === 0 && debouncedSearch.trim() ? (
                                                            <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                <span className="material-symbols-outlined text-[40px] mb-3">person_search</span>
                                                                <p className="text-[14px] font-medium text-slate-500">No clients found</p>
                                                                <p className="text-[12px] text-slate-400 mt-1">Try a different search term or create a new client.</p>
                                                            </div>
                                                        ) : searchResults.length === 0 ? (
                                                            <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                <span className="material-symbols-outlined text-[40px] mb-3">search</span>
                                                                <p className="text-[14px] font-medium text-slate-500">No clients available</p>
                                                                <p className="text-[12px] text-slate-400 mt-1">Create a new client to get started.</p>
                                                            </div>
                                                        ) : viewMode === 'list' ? (
                                                            <div className="space-y-1">
                                                                {searchResults.map((c) => (
                                                                    <button
                                                                        key={c.id}
                                                                        type="button"
                                                                        onClick={() => handleClientSelect(c)}
                                                                        className="flex w-full items-center gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition-all hover:border-indigo-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                    >
                                                                        <div className="h-10 w-10 shrink-0 rounded-full overflow-hidden flex items-center justify-center bg-indigo-100">
                                                                            <span className="text-sm font-semibold text-indigo-700 select-none">
                                                                                {getInitial(c.first_name)}
                                                                            </span>
                                                                        </div>
                                                                        <div className="min-w-0 flex-1 grid grid-cols-5 gap-2 text-[13px]">
                                                                            <div className="col-span-2">
                                                                                <p className="font-bold text-slate-900 truncate">{c.full_name}</p>
                                                                            </div>
                                                                            <div>
                                                                                <span className="text-slate-400 text-[11px]">Sex:</span>{' '}
                                                                                <span className="text-slate-700">{c.sex || '-'}</span>
                                                                            </div>
                                                                            <div>
                                                                                <span className="text-slate-400 text-[11px]">DOB:</span>{' '}
                                                                                <span className="text-slate-700">{c.date_of_birth || '-'}</span>
                                                                            </div>
                                                                            <div>
                                                                                <span className="text-slate-400 text-[11px]">Contact:</span>{' '}
                                                                                <span className="text-slate-700">{c.contact_number || '-'}</span>
                                                                            </div>
                                                                        </div>
                                                                        <div className="shrink-0 text-right text-[12px]">
                                                                            {c.case_count > 0 ? (
                                                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-3 py-0.5 text-amber-700 font-medium">
                                                                                    <span className="material-symbols-outlined text-[14px]">description</span>
                                                                                    {c.case_count} {c.case_count === 1 ? 'case' : 'cases'}
                                                                                </span>
                                                                            ) : (
                                                                                <span className="text-slate-400">No cases</span>
                                                                            )}
                                                                        </div>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : viewMode === 'grid' ? (
                                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {searchResults.map((c) => (
                                                                    <button
                                                                        key={c.id}
                                                                        type="button"
                                                                        onClick={() => handleClientSelect(c)}
                                                                        className="flex w-full items-start gap-4 rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-indigo-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                    >
                                                                        <div className="h-12 w-12 shrink-0 rounded-full overflow-hidden flex items-center justify-center bg-indigo-100">
                                                                            <span className="text-sm font-semibold text-indigo-700 select-none">
                                                                                {getInitial(c.first_name)}
                                                                            </span>
                                                                        </div>
                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="text-[14px] font-bold text-slate-900 truncate">{c.full_name}</p>
                                                                            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-slate-500">
                                                                                {c.case_count > 0 ? (
                                                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2 py-0.5 text-amber-700 font-medium text-[11px]">
                                                                                        <span className="material-symbols-outlined text-[13px]">description</span>
                                                                                        {c.case_count} {c.case_count === 1 ? 'case' : 'cases'}
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="text-slate-400">No cases</span>
                                                                                )}
                                                                                {c.sex && <span>Sex: <span className="font-medium text-slate-700">{c.sex}</span></span>}
                                                                                {c.date_of_birth && <span>DOB: <span className="font-medium text-slate-700">{c.date_of_birth}</span></span>}
                                                                            </div>
                                                                        </div>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : null}

                                                        <p className="mt-3 text-[11px] text-slate-400 text-center">Type to search existing clients. Showing up to 20 matching results.</p>
                                                    </div>
                                                )}
                                            </Subsection>
                                        </div>

                                    {clientSource === 'new' && (
                                        <>
                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Client Information">
                                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                    <Field label="First Name" required>
                                                        <Input value={data.client.first_name} onChange={(e) => handleClientChange('first_name', e.target.value)} required maxLength={255} />
                                                    </Field>
                                                    <Field label="Middle Initial">
                                                        <Input value={data.client.middle_initial} onChange={(e) => handleClientChange('middle_initial', e.target.value)} maxLength={1} />
                                                    </Field>
                                                    <Field label="Last Name" required>
                                                        <Input value={data.client.last_name} onChange={(e) => handleClientChange('last_name', e.target.value)} required maxLength={255} />
                                                    </Field>
                                                    <Field label="Suffix">
                                                        <Select value={data.client.suffix} onChange={(e) => handleClientChange('suffix', e.target.value)} options={SUFFIX_OPTIONS.filter(Boolean).map((s) => ({ label: s, value: s }))} placeholder="None" />
                                                    </Field>
                                                    <Field label="Date of Birth" required>
                                                        <Input type="date" value={data.client.date_of_birth} onChange={(e) => handleClientChange('date_of_birth', e.target.value)} required />
                                                    </Field>
                                                    <Field label="Sex" required>
                                                        <Select value={data.client.sex} onChange={(e) => handleClientChange('sex', e.target.value)} options={[{ label: 'Male', value: 'Male' }, { label: 'Female', value: 'Female' }]} required />
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
                                                                    toast.error('Please provide a valid OFW email address.');
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
                                                    <Field label="Last Job Position" required>
                                                        <Input value={data.employment.last_position} onChange={(e) => handleEmploymentChange('last_position', e.target.value)} />
                                                    </Field>
                                                    <Field label="Employment Period" required className="md:col-span-2">
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="date"
                                                                value={data.employment.start_date}
                                                                onChange={(e) => handleEmploymentChange('start_date', e.target.value)}
                                                                className="h-10 flex-1 min-w-0 rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />
                                                            <span className="text-[11px] font-bold text-slate-400 shrink-0">to</span>
                                                            {data.employment.is_present ? (
                                                                <span className="h-10 flex-1 min-w-0 rounded-[3px] border border-slate-200 bg-slate-50 px-3 flex items-center text-[13px] font-medium text-emerald-700">
                                                                    Present
                                                                </span>
                                                            ) : (
                                                                <input
                                                                    type="date"
                                                                    value={data.employment.end_date}
                                                                    onChange={(e) => handleEmploymentChange('end_date', e.target.value)}
                                                                    min={data.employment.start_date || undefined}
                                                                    className="h-10 flex-1 min-w-0 rounded-[3px] border border-slate-300 px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
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
                                                                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
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

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Next of Kin Information">
                                                <p className="mb-3 text-[13px] text-slate-500">Add one or more next of kin contacts. The first entry is auto-selected as primary.</p>

                                                {data.next_of_kin.length === 0 && (
                                                    <p className="mb-4 text-[13px] text-slate-400">No next of kin added. Click "Add Next of Kin" to add one.</p>
                                                )}

                                                {data.next_of_kin.map((nok, idx) => (
                                                    <div key={idx} className="mb-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                        <div className="mb-4 flex items-center justify-between">
                                                            <h4 className="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-700">
                                                                Next of Kin #{idx + 1}
                                                            </h4>
                                                            <div className="flex items-center gap-3">
                                                                <label className="flex cursor-pointer items-center gap-1.5 text-[11px] font-bold text-slate-600">
                                                                    <input
                                                                        type="radio"
                                                                        name="primary-nok"
                                                                        checked={nok.is_primary}
                                                                        onChange={() => setPrimaryNok(idx)}
                                                                        className="h-3.5 w-3.5 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                                    />
                                                                    Primary
                                                                </label>
                                                                {data.next_of_kin.length > 0 && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeNok(idx)}
                                                                        className="text-red-400 hover:text-red-600 transition-colors"
                                                                        title="Remove"
                                                                    >
                                                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                                            <polyline points="3 6 5 6 21 6" />
                                                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                                            <path d="M10 11v6" />
                                                                            <path d="M14 11v6" />
                                                                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                                        </svg>
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                            <Field label="First Name">
                                                                <Input value={nok.first_name} onChange={(e) => updateNokField(idx, 'first_name', e.target.value)} />
                                                            </Field>
                                                            <Field label="Middle Initial">
                                                                <Input value={nok.middle_initial} onChange={(e) => updateNokField(idx, 'middle_initial', e.target.value)} maxLength={1} />
                                                            </Field>
                                                            <Field label="Last Name">
                                                                <Input value={nok.last_name} onChange={(e) => updateNokField(idx, 'last_name', e.target.value)} />
                                                            </Field>
                                                            <Field label="Relationship">
                                                                <Select value={nok.relationship} onChange={(e) => updateNokField(idx, 'relationship', e.target.value)}
                                                                    options={['Mother', 'Father', 'Spouse', 'Sibling', 'Other'].map((r) => ({ label: r, value: r }))}
                                                                    placeholder="Select relationship" />
                                                            </Field>
                                                            <Field label="Phone Number" className="md:col-span-2">
                                                                <PhoneInput value={nok.phone_number} onChange={(val) => updateNokField(idx, 'phone_number', val)} className="min-w-0" />
                                                            </Field>
                                                            <Field label="Email" className="md:col-span-2">
                                                                <Input type="email" value={nok.email} onChange={(e) => updateNokField(idx, 'email', e.target.value)} />
                                                            </Field>
                                                        </div>

                                                        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                            <Subsection title="Address">
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
                                                            </Subsection>
                                                        </div>
                                                    </div>
                                                ))}

                                                <button
                                                    type="button"
                                                    onClick={addNok}
                                                    className="inline-flex items-center gap-2 rounded-md border border-dashed border-slate-300 bg-white px-4 py-2 text-[13px] font-bold text-slate-500 transition hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50"
                                                >
                                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <line x1="12" y1="5" x2="12" y2="19" />
                                                        <line x1="5" y1="12" x2="19" y2="12" />
                                                    </svg>
                                                    Add Next of Kin
                                                </button>
                                            </Subsection>
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
                                                    <input type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-700 focus:ring-amber-600" />
                                                    <span>I acknowledge and confirm client consent for data use in the system.</span>
                                                </label>
                                                {!data.consent && <p className="mt-3 text-[12px] font-medium text-amber-800">Required to create a case for a new client.</p>}
                                            </div>
                                        )}
                                    </>
                                    )}
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
                                                <Field label="Client Type" required>
                                                    <select
                                                        value={data.client_type}
                                                        onChange={(e) => setData('client_type', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        required
                                                    >
                                                        <option value="OFW">Overseas Filipino Worker</option>
                                                        <option value="NEXT_OF_KIN">Next of Kin</option>
                                                    </select>
                                                </Field>
                                                <Field label="Category" required>
                                                    <select
                                                        value={data.category_id}
                                                        onChange={(e) => setData('category_id', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        required
                                                    >
                                                        <option value="">Select category</option>
                                                        {categories.map((cat) => (
                                                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                        ))}
                                                    </select>
                                                </Field>
                                            </div>
                                            {data.client_type === 'NEXT_OF_KIN' && (
                                                <div className="mt-4 pt-4 border-t border-slate-200">
                                                    {data.next_of_kin.length > 0 ? (
                                                        <Field label="Select Next of Kin" required>
                                                            <select
                                                                value={data.selected_nok_index}
                                                                onChange={(e) => {
                                                                    setData('selected_nok_index', e.target.value);
                                                                    clearErrors('selected_nok_index', 'next_of_kin.email');
                                                                }}
                                                                className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                                required
                                                            >
                                                                <option value="">Select next of kin...</option>
                                                                {data.next_of_kin.map((nok, idx) => (
                                                                    <option key={idx} value={String(idx)}>
                                                                        {[nok.first_name, nok.last_name].filter(Boolean).join(' ')}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                            <InputError message={errors.selected_nok_index} className="mt-1" />
                                                        </Field>
                                                    ) : (
                                                        <p className="text-[13px] text-amber-600 bg-amber-50 rounded-md px-4 py-3 border border-amber-200">
                                                            Please add Next of Kin entries in Step 1 first.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                            <div className="mt-5 pt-5 border-t border-slate-200">
                                                {data.client_type === 'NEXT_OF_KIN' ? (
                                                    <Field label="Selected Next of Kin Email Address" required>
                                                        <div className="flex h-10 w-full items-center rounded-[3px] border border-slate-300 bg-slate-50 px-3 text-[13px] text-slate-700">
                                                            {selectedNok?.email || 'Select a next of kin with an email address'}
                                                        </div>
                                                        <InputError message={errors['next_of_kin.email']} className="mt-1" />
                                                    </Field>
                                                ) : (
                                                    <Field label="OFW Email Address" required>
                                                        <div className="flex h-10 w-full items-center rounded-[3px] border border-slate-300 bg-slate-50 px-3 text-[13px] text-slate-700">
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
                                                    <select
                                                        value={data.vulnerability_indicator}
                                                        onChange={(e) => setData('vulnerability_indicator', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                        required
                                                    >
                                                        <option value="">Select vulnerability...</option>
                                                        <option value="PWD">PWD</option>
                                                        <option value="Senior Citizen">Senior Citizen</option>
                                                        <option value="Solo Parent">Solo Parent</option>
                                                        <option value="Indigenous Person">Indigenous Person</option>
                                                        <option value="None">None</option>
                                                    </select>
                                                </Field>
                                                {data.client_type === 'NEXT_OF_KIN' && (
                                                    <Field label="Next of Kin Vulnerability Status" required>
                                                        <select
                                                            value={data.nok_vulnerability_indicator}
                                                            onChange={(e) => setData('nok_vulnerability_indicator', e.target.value)}
                                                            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            required
                                                        >
                                                            <option value="">Select vulnerability...</option>
                                                            <option value="PWD">PWD</option>
                                                            <option value="Senior Citizen">Senior Citizen</option>
                                                            <option value="Solo Parent">Solo Parent</option>
                                                            <option value="Indigenous Person">Indigenous Person</option>
                                                            <option value="None">None</option>
                                                        </select>
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
                                                        <select
                                                            value={data.case_issue_id}
                                                            onChange={(e) => setData('case_issue_id', e.target.value)}
                                                            className="h-10 flex-1 rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            required
                                                        >
                                                            <option value="">Select issue/concern...</option>
                                                            {localIssues.map((issue) => (
                                                                <option key={issue.id} value={issue.id}>{issue.name}</option>
                                                            ))}
                                                        </select>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setNewIssueName(''); setShowAddIssue(!showAddIssue); }}
                                                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[3px] border border-dashed border-indigo-300 text-indigo-600 transition hover:bg-indigo-50"
                                                            title="Add new issue"
                                                        >
                                                            <span className="material-symbols-outlined text-[20px]">add</span>
                                                        </button>
                                                    </div>
                                                </Field>
                                            </div>
                                            {showAddIssue && (
                                                <div className="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                                    <Field label="New Issue Name">
                                                        <input
                                                            type="text"
                                                            value={newIssueName}
                                                            onChange={(e) => setNewIssueName(e.target.value)}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                                                            placeholder="Enter new issue name..."
                                                            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            autoFocus
                                                        />
                                                    </Field>
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={handleQuickAddIssue}
                                                            disabled={addingIssue || !newIssueName.trim()}
                                                            className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-[12px] font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
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
                                                        className="w-full rounded-[3px] border border-slate-300 px-3 py-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
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
                                                        <select
                                                            value={data.case_issue_id}
                                                            onChange={(e) => setData('case_issue_id', e.target.value)}
                                                            className="h-10 flex-1 rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            required
                                                        >
                                                            <option value="">Select issue/concern...</option>
                                                            {localIssues.map((issue) => (
                                                                <option key={issue.id} value={issue.id}>{issue.name}</option>
                                                            ))}
                                                        </select>
                                                        <button
                                                            type="button"
                                                            onClick={() => { setNewIssueName(''); setShowAddIssue(!showAddIssue); }}
                                                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[3px] border border-dashed border-indigo-300 text-indigo-600 transition hover:bg-indigo-50"
                                                            title="Add new issue"
                                                        >
                                                            <span className="material-symbols-outlined text-[20px]">add</span>
                                                        </button>
                                                    </div>
                                                </Field>
                                            </div>
                                            {showAddIssue && (
                                                <div className="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                                    <Field label="New Issue Name">
                                                        <input
                                                            type="text"
                                                            value={newIssueName}
                                                            onChange={(e) => setNewIssueName(e.target.value)}
                                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleQuickAddIssue(); } }}
                                                            placeholder="Enter new issue name..."
                                                            className="h-10 w-full rounded-[3px] border border-slate-300 px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            autoFocus
                                                        />
                                                    </Field>
                                                    <div className="mt-2 flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={handleQuickAddIssue}
                                                            disabled={addingIssue || !newIssueName.trim()}
                                                            className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-[12px] font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
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

                        <div className="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
                            <div className="flex items-center gap-2">
                                <button type="button" onClick={handleBack} disabled={currentStep === 1}
                                    className="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-5 py-2.5 text-[13px] font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">chevron_left</span> Back
                                </button>
                                <div className="flex items-center gap-2">
                                    <button type="button" onClick={handleSaveDraft} disabled={processing || autoSaveStatus === 'saving'}
                                        className="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-5 py-2.5 text-[13px] font-bold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                        <span className="material-symbols-outlined text-[18px]">save</span>
                                        {processing ? (existingDraft ? 'Updating...' : 'Saving...') : (existingDraft ? 'Update Draft' : 'Save as Draft')}
                                    </button>
                                    {autoSaveStatus === 'saving' && (
                                        <span className="text-[12px] text-slate-500 animate-pulse">Saving...</span>
                                    )}
                                    {autoSaveStatus === 'saved' && (
                                        <span className="text-[12px] text-green-600">Draft saved</span>
                                    )}
                                    {autoSaveStatus === 'error' && (
                                        <span className="text-[12px] text-red-500">Save failed</span>
                                    )}
                                </div>
                            </div>
                            {currentStep < 3 ? (
                                <div className="flex flex-col items-end gap-1">
                                    <button type="button" onClick={handleNext} disabled={!canProceed()}
                                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                        Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                    </button>
                                    {!canProceed() && getMissingFields().length > 0 && (
                                        <span className="text-[11px] text-amber-600 text-right leading-tight">
                                            Complete: {getMissingFields().join(', ')}
                                        </span>
                                    )}
                                </div>
                            ) : (
                                <button type="button" onClick={handleSubmit} disabled={processing || !canSubmit()}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-6 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    {processing ? (existingDraft ? 'Publishing...' : 'Creating...') : (existingDraft ? 'Publish Draft' : 'Create Case')}
                                </button>
                            )}
                        </div>
                    </div>
                </section>
            </form>
            <ClientProfileSummaryModal show={!!selectedClient} client={selectedClient} onConfirm={handleConfirmClient} onClose={() => setSelectedClient(null)} />
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
            <CaseSummaryModal
                show={showCreateModal}
                data={data}
                caseId={caseId}
                trackingId={trackingId}
                categories={categories}
                caseIssues={caseIssues}
                notificationEmail={notificationEmail}
                onClose={() => setShowCreateModal(false)}
                onConfirm={handleConfirmSubmit}
                processing={processing}
                isDraft={!!existingDraft}
                nokSummary={nokSummary}
            />
        </AppLayout>
    );
}
