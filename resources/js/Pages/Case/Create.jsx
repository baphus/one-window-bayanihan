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
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import ClientProfileSummaryModal from '@/Components/ClientProfileSummaryModal';

const STEPS = [
    { id: 1, title: 'Client Profile', description: 'Enter client information and vulnerability status' },
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
        <div className={className}>
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
    const { client, existingClients = [], categories = [], existingDraft, auth } = usePage().props;

    const { data, setData, post, put, processing, errors, clearErrors } = useForm({
        client_type: 'OFW',
        category_id: '',
        vulnerability_indicator: '',
        nok_vulnerability_indicator: '',
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
            nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
        },
        consent: false,
        has_next_of_kin: true,
        selected_client_id: '',
        is_draft: false,
    });

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

    const initialFormRef = useRef({
        formData: {
            client_type: 'OFW',
            category_id: '',
            vulnerability_indicator: '',
            nok_vulnerability_indicator: '',
            summary: '',
            client: { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: 'Male', email: '', contact_number: '' },
            address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
            employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '' },
            next_of_kin: { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '', nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } },
            consent: false,
            has_next_of_kin: true,
            is_draft: false,
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
            && a.next_of_kin.nok_address.region === b.next_of_kin.nok_address.region
            && a.next_of_kin.nok_address.province === b.next_of_kin.nok_address.province
            && a.next_of_kin.nok_address.city_municipality === b.next_of_kin.nok_address.city_municipality
            && a.next_of_kin.nok_address.barangay === b.next_of_kin.nok_address.barangay
            && a.next_of_kin.nok_address.street === b.next_of_kin.nok_address.street
            && a.consent === b.consent
            && a.has_next_of_kin === b.has_next_of_kin
            && a.is_draft === b.is_draft;
    }

    const hasDirty = useMemo(() => {
        return !formDataEqual(data, initialFormRef.current.formData)
            || clientSource !== initialFormRef.current.clientSource;
    }, [data, clientSource]);
    const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasDirty);

    const { autoSaveStatus, saveOnStepChange, draftId: autoSaveDraftId, setDraftId } = useAutoSave({
        formData: data,
        draftId: existingDraft?.id || null,
        step: currentStep,
    });

    const { hasLocalBackup, localBackup, clearLocalBackup } = useLocalStorageDraft({
        formData: data,
        userId: auth.user?.id,
        enabled: !existingDraft && clientSource === 'new',
    });

    useEffect(() => {
        if (!hasLocalBackup || existingDraft || !localBackup?.data || restoredRef.current) return;
        restoredRef.current = true;

        const backupData = localBackup.data;

        if (backupData.client) setData('client', { ...backupData.client });
        if (backupData.address) setData('address', { ...backupData.address });
        if (backupData.employment) setData('employment', { ...backupData.employment });
        if (backupData.next_of_kin) setData('next_of_kin', { ...backupData.next_of_kin });
        setData('client_type', backupData.client_type || 'OFW');
        setData('category_id', backupData.category_id || '');
        setData('vulnerability_indicator', backupData.vulnerability_indicator || '');
        setData('nok_vulnerability_indicator', backupData.nok_vulnerability_indicator || '');
        setData('summary', backupData.summary || '');
        setData('consent', backupData.consent ?? false);
        setData('has_next_of_kin', backupData.has_next_of_kin ?? true);

        initialFormRef.current = {
            formData: backupData,
            clientSource: 'new',
        };
    }, [hasLocalBackup, existingDraft, localBackup, setData]);

    useEffect(() => {
        if (autoSaveDraftId) draftIdRef.current = autoSaveDraftId;
    }, [autoSaveDraftId]);

    const filteredClients = useMemo(() => {
        const q = debouncedSearch.trim().toLowerCase();
        if (!q) return existingClients.slice(0, 200);
        return existingClients.filter((c) => {
            const searchStr = [c.first_name, c.middle_name, c.last_name].filter(Boolean).join(' ').toLowerCase();
            return searchStr.includes(q);
        }).slice(0, 200);
    }, [existingClients, debouncedSearch]);

    useEffect(() => {
        if (client) {
            setClientSource('existing');
            setData('selected_client_id', client.id);
            setData('client', {
                ...data.client,
                first_name: client.first_name || '',
                last_name: client.last_name || '',
                middle_name: client.middle_name || '',
                suffix: client.suffix || '',
                date_of_birth: client.date_of_birth || '',
                sex: client.sex || 'Male',
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
            }

            // Update initial ref so dirty tracking starts from pre-filled state
            initialFormRef.current = {
                formData: {
                    client_type: 'OFW',
                    category_id: '',
                    vulnerability_indicator: '',
                    nok_vulnerability_indicator: '',
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
                        nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
                    },
                    consent: false,
                    has_next_of_kin: true,
                    is_draft: false,
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
        setData('vulnerability_indicator', existingDraft.vulnerability_indicator || '');
        setData('summary', existingDraft.summary || '');
        setData('is_draft', true);

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
                middle_name: existingDraft.client.middle_name || '',
                suffix: existingDraft.client.suffix || '',
                date_of_birth: existingDraft.client.date_of_birth || '',
                sex: existingDraft.client.sex || '',
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

            if (existingDraft.client.nextOfKin?.[0]) {
                setData('next_of_kin', {
                    ...data.next_of_kin,
                    first_name: existingDraft.client.nextOfKin[0].first_name || '',
                    middle_initial: existingDraft.client.nextOfKin[0].middle_initial || '',
                    last_name: existingDraft.client.nextOfKin[0].last_name || '',
                    relationship: existingDraft.client.nextOfKin[0].relationship || '',
                    phone_number: existingDraft.client.nextOfKin[0].phone_number || '',
                    email: existingDraft.client.nextOfKin[0].email || '',
                    full_address: existingDraft.client.nextOfKin[0].full_address || '',
                });
            }
        } else if (existingDraft.draft_client_data) {
            // JSON blob from new-client drafts
            try {
                clientData = typeof existingDraft.draft_client_data === 'string'
                    ? JSON.parse(existingDraft.draft_client_data)
                    : existingDraft.draft_client_data;
            } catch {
                clientData = null;
            }

            if (clientData) {
                setData('client', {
                    ...data.client,
                    first_name: clientData.first_name || '',
                    last_name: clientData.last_name || '',
                    middle_name: clientData.middle_name || '',
                    suffix: clientData.suffix || '',
                    date_of_birth: clientData.date_of_birth || '',
                    sex: clientData.sex || '',
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
                    setData('next_of_kin', {
                        ...data.next_of_kin,
                        first_name: clientData.next_of_kin.first_name || '',
                        middle_initial: clientData.next_of_kin.middle_initial || '',
                        last_name: clientData.next_of_kin.last_name || '',
                        relationship: clientData.next_of_kin.relationship || '',
                        phone_number: clientData.next_of_kin.phone_number || '',
                        email: clientData.next_of_kin.email || '',
                        full_address: clientData.next_of_kin.full_address || '',
                    });
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

        // 4. Generated IDs — override with existing draft's real IDs
        setCaseId(existingDraft.case_number);
        setTrackingId(existingDraft.tracker_number);

        // 5. Dirty tracking reset — follow handleConfirmClient pattern
        initialFormRef.current = {
            formData: {
                client_type: existingDraft.client_type || 'OFW',
                category_id: existingDraft.category_id || '',
                vulnerability_indicator: existingDraft.vulnerability_indicator || '',
                nok_vulnerability_indicator: '',
                summary: existingDraft.summary || '',
                client: {
                    first_name: c.first_name || '',
                    last_name: c.last_name || '',
                    middle_name: c.middle_name || '',
                    suffix: c.suffix || '',
                    date_of_birth: c.date_of_birth || '',
                    sex: c.sex || '',
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
                next_of_kin: {
                    first_name: (noks[0]?.first_name || c.next_of_kin?.first_name) || '',
                    middle_initial: (noks[0]?.middle_initial || c.next_of_kin?.middle_initial) || '',
                    last_name: (noks[0]?.last_name || c.next_of_kin?.last_name) || '',
                    is_primary: false,
                    relationship: (noks[0]?.relationship || c.next_of_kin?.relationship) || '',
                    phone_number: (noks[0]?.phone_number || c.next_of_kin?.phone_number) || '',
                    email: (noks[0]?.email || c.next_of_kin?.email) || '',
                    full_address: (noks[0]?.full_address || c.next_of_kin?.full_address) || '',
                    nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
                },
                consent: false,
                has_next_of_kin: !!(noks[0] || c.next_of_kin),
                is_draft: true,
            },
            clientSource: src,
        };
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
            setData('next_of_kin', { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '', nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } });
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
        }
    }

    function handleNokAddressChange(field, value) {
        if (typeof field === 'object') {
            setData('next_of_kin', { ...data.next_of_kin, nok_address: { ...data.next_of_kin.nok_address, ...field } });
        } else {
            setData('next_of_kin', { ...data.next_of_kin, nok_address: { ...data.next_of_kin.nok_address, [field]: value } });
        }
    }

    function handleEmploymentChange(field, value) {
        setData('employment', { ...data.employment, [field]: value });
    }

    async function handleNext() {
        if (currentStep < 3) {
            const result = await saveOnStepChange(currentStep, currentStep + 1);
            if (result?.id) {
                setDraftId(result.id);
                draftIdRef.current = result.id;
            }
            clearErrors();
            setCurrentStep((prev) => prev + 1);
        }
    }

    async function handleBack() {
        if (currentStep > 1) {
            const result = await saveOnStepChange(currentStep, currentStep - 1);
            if (result?.id) {
                setDraftId(result.id);
                draftIdRef.current = result.id;
            }
            clearErrors();
            setCurrentStep((prev) => prev - 1);
        }
    }

    function canProceed() {
        if (currentStep === 1) {
            const base = data.client.first_name.trim().length > 0 && data.client.last_name.trim().length > 0;
            return clientSource === 'new' ? (base && data.client.email.trim().length > 0) : base;
        }
        if (currentStep === 2) return true;
        return true;
    }

    function canSubmit() {
        return data.client.first_name.trim().length > 0
            && data.client.last_name.trim().length > 0
            && (clientSource === 'existing' || (data.client.email.trim().length > 0 && data.consent));
    }

    function handleSaveDraft(e) {
        e.preventDefault();
        bypassNext();

        const submitData = {
            ...data,
            has_next_of_kin: undefined,
            is_draft: true,
        };

        if (existingDraft) {
            put(route('cases.save-draft', existingDraft.id), {
                data: submitData,
                onSuccess: () => { },
                onError: (errors) => {
                    console.error('Validation failed:', errors);
                },
                preserveState: false,
                preserveScroll: true,
            });
        } else {
            post(route('cases.store'), {
                data: submitData,
                onSuccess: () => { },
                onError: (errors) => {
                    console.error('Validation failed:', errors);
                },
                preserveState: false,
                preserveScroll: true,
            });
        }
    }

    function handleSubmit(e) {
        e.preventDefault();
        bypassNext();

        if (existingDraft) {
            // Publishing does NOT send form data — publishes the draft as last saved.
            // User should save via "Update Draft" first.
            post(route('cases.publish', existingDraft.id), {
                onSuccess: () => { clearLocalBackup(); },
                onError: (errors) => {
                    console.error('Publish failed:', errors);
                },
                preserveScroll: true,
            });
            return;
        }

        post(route('cases.store'), {
            data: { ...data, has_next_of_kin: undefined, is_draft: false },
            onSuccess: () => { clearLocalBackup(); },
            onError: (errors) => {
                console.error('Validation failed:', errors);
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
        setData('client', { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: '', email: '', contact_number: '' });
        setData('address', { region: '', province: '', city_municipality: '', barangay: '', street: '' });
        setData('employment', { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '' });
        setData('next_of_kin', { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '', nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } });
        setData('consent', false);
        setSelectedClient(null);
        setSearchQuery('');
        setDebouncedSearch('');
        initialFormRef.current = {
            formData: {
                client_type: 'OFW',
                category_id: '',
                vulnerability_indicator: '',
                nok_vulnerability_indicator: '',
                summary: '',
                client: { first_name: '', last_name: '', middle_name: '', suffix: '', date_of_birth: '', sex: '', email: '', contact_number: '' },
                address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
                employment: { employer_name: '', position: '', country: '', start_date: '', end_date: '', last_country: '', last_position: '', date_of_arrival: '' },
                next_of_kin: { first_name: '', middle_initial: '', last_name: '', is_primary: false, relationship: '', phone_number: '', email: '', full_address: '', nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } },
                consent: false,
                has_next_of_kin: true,
                is_draft: false,
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
        middle_name: client.middle_name || '',
        suffix: client.suffix || '',
        date_of_birth: client.date_of_birth || '',
        sex: client.sex || '',
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

    if (client.nextOfKin?.[0]) {
        const nok = client.nextOfKin[0];
        setData('next_of_kin', {
            ...data.next_of_kin,
            first_name: nok.first_name || '',
            middle_initial: nok.middle_initial || '',
            last_name: nok.last_name || '',
            relationship: nok.relationship || '',
            phone_number: nok.phone_number || '',
            email: nok.email || '',
            full_address: nok.full_address || '',
        });
    }

    // CRITICAL: Update initialFormRef so dirty tracking starts from pre-filled state
    initialFormRef.current = {
        formData: {
            client_type: 'OFW',
            category_id: '',
            vulnerability_indicator: '',
            nok_vulnerability_indicator: '',
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
                start_date: '',
                end_date: '',
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
                nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
            },
            consent: false,
            has_next_of_kin: true,
            is_draft: false,
        },
        clientSource: 'existing',
    };

    setSelectedClient(null);
}

    const nokSummary = data.has_next_of_kin
        ? [data.next_of_kin.first_name, data.next_of_kin.last_name].filter(Boolean).join(' ') || 'Not yet provided'
        : 'No next of kin indicated';

    return (
        <AppLayout title={existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'}>
            <Head title={existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'} />

            {Object.keys(errors).length > 0 && (
                <div className="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
                    <h3 className="text-sm font-bold text-red-800">Unable to create case</h3>
                    <ul className="mt-2 list-disc pl-5 text-sm text-red-700">
                        {Object.entries(errors).map(([field, message]) => (
                            <li key={field}>{field}: {message}</li>
                        ))}
                    </ul>
                </div>
            )}

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
                        <h1 className="text-2xl font-bold text-slate-900">{existingDraft ? `Editing Draft: ${existingDraft.case_number}` : 'Create New Case'}</h1>
                        <p className="text-sm text-slate-500 mt-1">{existingDraft ? 'Continue editing your draft case before submitting or publishing.' : 'A guided onboarding flow to register the case with confidence.'}</p>
                    </div>
                    <Link href={existingDraft ? route('cases.drafts') : route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">&larr; {existingDraft ? 'Back to Drafts' : 'Back to Cases'}</Link>
                </div>
            </div>

            <form onSubmit={handleSubmit} onKeyDown={(e) => {
                if (e.key === 'Enter' && currentStep < 3 && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    if (canProceed()) handleNext();
                }
            }}>
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
                                    {currentStep === 1 ? 'Fill client details' : currentStep === 2 ? 'Get oriented' : 'Tell the story'}
                                </h4>
                                <ul className="mt-3 space-y-2 text-[13px] text-slate-600">
                                    {currentStep === 1 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Fill in complete client details.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Add work history and next of kin if applicable.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Indicate any vulnerability status.</span></li>
                                        </>
                                    )}
                                    {currentStep === 2 && (
                                        <>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>We generate the case number and tracking ID for you.</span></li>
                                            <li className="flex gap-2"><span className="mt-1 h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0" /><span>Choose the right client type.</span></li>
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
                                                                className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                            />
                                                        </div>

                                                        <div className="mb-4 flex items-center justify-between">
                                                            <span className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{filteredClients.length} client(s) found</span>
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

                                                        {filteredClients.length === 0 ? (
                                                            <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                                                                <span className="material-symbols-outlined text-[40px] mb-3">person_search</span>
                                                                <p className="text-[14px] font-medium text-slate-500">No clients found</p>
                                                                <p className="text-[12px] text-slate-400 mt-1">Try a different search term or create a new client.</p>
                                                            </div>
                                                        ) : viewMode === 'grid' ? (
                                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                                {filteredClients.map((c) => (
                                                                    <button
                                                                        key={c.id}
                                                                        type="button"
                                                                        onClick={() => setSelectedClient(c)}
                                                                        className="flex w-full items-start gap-4 rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-indigo-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                    >
                                                                        <div className="h-12 w-12 shrink-0 rounded-full overflow-hidden flex items-center justify-center bg-indigo-100">
                                                                            <span className="text-sm font-semibold text-indigo-700 select-none">
                                                                                {getInitial(c.first_name)}
                                                                            </span>
                                                                        </div>
                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="text-[14px] font-bold text-slate-900 truncate">{c.full_name}</p>
                                                                            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-slate-500">
                                                                                {c.has_case && <span className="font-medium text-amber-600">Has existing case</span>}
                                                                                {c.sex && <span>Sex: <span className="font-medium text-slate-700">{c.sex}</span></span>}
                                                                                {c.date_of_birth && <span>DOB: <span className="font-medium text-slate-700">{c.date_of_birth}</span></span>}
                                                                            </div>
                                                                        </div>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <UnifiedTable
                                                                data={filteredClients}
                                                                columns={[
                                                                    { key: 'name', title: 'Name', render: (row) => row.full_name },
                                                                    { key: 'sex', title: 'Sex' },
                                                                    { key: 'dob', title: 'Date of Birth', render: (row) => row.date_of_birth || '-' },
                                                                    { key: 'contact', title: 'Contact', render: (row) => row.contact_number || '-' },
                                                                    { key: 'case', title: 'Case Status', render: (row) => row.has_case ? <span className="font-medium text-amber-600">Has existing case</span> : '-' },
                                                                ]}
                                                                keyExtractor={(row) => row.id}
                                                                hideControlBar
                                                                hidePagination
                                                                onRowClick={(row) => setSelectedClient(row)}
                                                            />
                                                        )}

                                                        <p className="mt-3 text-[11px] text-slate-400 text-center">Showing up to 200 clients. Refine search to find specific clients.</p>
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
                                                        <Select value={data.client.sex} onChange={(e) => handleClientChange('sex', e.target.value)} options={[{ label: 'Male', value: 'Male' }, { label: 'Female', value: 'Female' }]} />
                                                    </Field>
                                                    <Field label="Email Address" required>
                                                        <Input type="email" value={data.client.email} onChange={(e) => handleClientChange('email', e.target.value)} />
                                                    </Field>
                                                    <div className="col-span-4 -mt-2">
                                                        <p className="flex items-center gap-1.5 text-[11px] text-amber-700">
                                                            <span className="material-symbols-outlined text-[14px]">info</span>
                                                            This email will be used to send case notifications and updates to the client.
                                                        </p>
                                                    </div>
                                                    <Field label="Contact Number">
                                                        <PhoneInput value={data.client.contact_number} onChange={(val) => handleClientChange('contact_number', val)} />
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
                                                        <CountrySelect value={data.employment.last_country} onChange={(v) => handleEmploymentChange('last_country', v)} placeholder="Select country..." />
                                                    </Field>
                                                    <Field label="Last Job Position">
                                                        <Input value={data.employment.last_position} onChange={(e) => handleEmploymentChange('last_position', e.target.value)} />
                                                    </Field>
                                                    <Field label="Arrival Date in Philippines">
                                                        <Input type="date" value={data.employment.date_of_arrival} onChange={(e) => setData('employment.date_of_arrival', e.target.value)} />
                                                    </Field>
                                                </div>
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <Subsection title="Next of Kin Information">
                                                <div className="mb-4">
                                                    <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${data.has_next_of_kin ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="has-nok" className="sr-only" checked={data.has_next_of_kin} onChange={() => setData('has_next_of_kin', true)} /> Yes
                                                        </label>
                                                        <label className={`flex cursor-pointer items-center justify-center rounded-md px-6 py-1.5 text-[13px] font-bold transition-all ${!data.has_next_of_kin ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'}`}>
                                                            <input type="radio" name="has-nok" className="sr-only" checked={!data.has_next_of_kin} onChange={() => { setData('has_next_of_kin', false); setData('next_of_kin', { ...data.next_of_kin, first_name: '', last_name: '', relationship: '', phone_number: '' }); }} /> No
                                                        </label>
                                                    </div>
                                                </div>
                                                {data.has_next_of_kin && (
                                                    <>
                                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                            <Field label="First Name">
                                                                <Input value={data.next_of_kin.first_name} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, first_name: e.target.value })} />
                                                            </Field>
                                                            <Field label="Middle Initial">
                                                                <Input value={data.next_of_kin.middle_initial} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, middle_initial: e.target.value })} maxLength={10} />
                                                            </Field>
                                                            <Field label="Last Name">
                                                                <Input value={data.next_of_kin.last_name} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, last_name: e.target.value })} />
                                                            </Field>
                                                            <Field label="Relationship">
                                                                <Select value={data.next_of_kin.relationship} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, relationship: e.target.value })}
                                                                    options={['Mother', 'Father', 'Spouse', 'Sibling', 'Other'].map((r) => ({ label: r, value: r }))}
                                                                    placeholder="Select relationship" />
                                                            </Field>
                                                            <Field label="Phone Number" className="md:col-span-2">
                                                                <PhoneInput value={data.next_of_kin.phone_number} onChange={(val) => setData('next_of_kin', { ...data.next_of_kin, phone_number: val })} />
                                                            </Field>
                                                            <Field label="Email" className="md:col-span-2">
                                                                <Input type="email" value={data.next_of_kin.email} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, email: e.target.value })} />
                                                            </Field>
                                                        </div>
                                                        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                                            <Subsection title="Address">
                                                                <AddressDropdowns
                                                                    values={data.next_of_kin.nok_address}
                                                                    onChange={handleNokAddressChange}
                                                                />
                                                            </Subsection>
                                                        </div>
                                                    </>
                                                )}
                                            </Subsection>
                                        </div>

                                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                                            <h3 className="text-[12px] font-bold uppercase tracking-wider text-slate-500">Vulnerability Status</h3>
                                            <p className="mt-2 text-[13px] text-slate-500">Indicate if the client falls under any vulnerable sector.</p>
                                            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <Field label={data.client_type === 'OFW' ? 'OFW Vulnerability Status' : data.client_type === 'NEXT_OF_KIN' ? 'Client Vulnerability Status' : 'Vulnerability Indicator'}>
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
                                                <Field label="Next of Kin Vulnerability Status">
                                                    <select
                                                        value={data.nok_vulnerability_indicator}
                                                        onChange={(e) => setData('nok_vulnerability_indicator', e.target.value)}
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
                                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="OFW">Overseas Filipino Worker</option>
                                                        <option value="NEXT_OF_KIN">Next of Kin</option>
                                                    </select>
                                                </Field>
                                                <Field label="Category" required>
                                                    <select
                                                        value={data.category_id}
                                                        onChange={(e) => setData('category_id', e.target.value)}
                                                        className="h-10 w-full rounded-[3px] border border-[#cbd5e1] px-3 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="">Select category</option>
                                                        {categories.map((cat) => (
                                                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                        ))}
                                                    </select>
                                                </Field>
                                            </div>
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
                                <button type="button" onClick={handleNext} disabled={!canProceed()}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50">
                                    Next <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                                </button>
                            ) : (
                                <button type="submit" disabled={processing || !canSubmit()}
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
        </AppLayout>
    );
}
