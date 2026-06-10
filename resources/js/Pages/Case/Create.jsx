import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
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
    const { client, existingClients = [], categories = [], existingDraft } = usePage().props;

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
            nok_address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
        },
        consent: false,
        selected_client_id: '',
        is_draft: false,
    });

    const [currentStep, setCurrentStep] = useState(1);
    const [caseId, setCaseId] = useState(() => GenerateCaseId());
    const [trackingId, setTrackingId] = useState(() => GenerateTrackingId());
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
    const [selectedClient, setSelectedClient] = useState(null);
    const [viewMode, setViewMode] = useState('grid');
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const searchDebounceRef = useRef(null);

    const initialFormRef = useRef({
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

        // 3. Individual useState overrides
        const c = clientData || {};
        const emps = c.employments || [];
        const noks = c.nextOfKin || [];

        setClientSource(src);
        setData('selected_client_id', selId);
        setClientGender(c.sex || 'Male');
        setClientEmail(c.email || '');
        setClientContact(c.contact_number || '');
        setLastCountry((emps[0]?.last_country || emps[0]?.country || c.employment?.last_country || c.employment?.country) || '');
        setLastJob((emps[0]?.last_position || emps[0]?.position || c.employment?.last_position || c.employment?.position) || '');
        setArrivalDate((emps[0]?.date_of_arrival || c.employment?.date_of_arrival) || '');
        setNokFirstName((noks[0]?.first_name || c.next_of_kin?.first_name) || '');
        setNokLastName((noks[0]?.last_name || c.next_of_kin?.last_name) || '');
        setNokContact((noks[0]?.phone_number || c.next_of_kin?.phone_number) || '');
        setNokRelationship((noks[0]?.relationship || c.next_of_kin?.relationship) || '');
        setHasNextOfKin(!!(noks[0] || c.next_of_kin));
        setConsent(false);
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
                is_draft: true,
            },
            useState: {
                clientSource: src,
                nokFirstName: (noks[0]?.first_name || c.next_of_kin?.first_name) || '',
                nokLastName: (noks[0]?.last_name || c.next_of_kin?.last_name) || '',
                nokContact: (noks[0]?.phone_number || c.next_of_kin?.phone_number) || '',
                nokRelationship: (noks[0]?.relationship || c.next_of_kin?.relationship) || '',
                clientGender: c.sex || 'Male',
                clientEmail: c.email || '',
                clientContact: c.contact_number || '',
                lastCountry: (emps[0]?.last_country || emps[0]?.country || c.employment?.last_country || c.employment?.country) || '',
                lastJob: (emps[0]?.last_position || emps[0]?.position || c.employment?.last_position || c.employment?.position) || '',
                arrivalDate: (emps[0]?.date_of_arrival || c.employment?.date_of_arrival) || '',
                hasNextOfKin: !!(noks[0] || c.next_of_kin),
                consent: false,
            },
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

    function handleNext() {
        if (currentStep < 3) {
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

    function canProceed() {
        if (currentStep === 1) {
            const base = data.client.first_name.trim().length > 0 && data.client.last_name.trim().length > 0;
            return clientSource === 'new' ? (base && clientEmail.trim().length > 0) : base;
        }
        if (currentStep === 2) return true;
        return true;
    }

    function canSubmit() {
        return data.client.first_name.trim().length > 0
            && data.client.last_name.trim().length > 0
            && (clientSource === 'existing' || (clientEmail.trim().length > 0 && consent));
    }

    function handleSaveDraft(e) {
        e.preventDefault();
        bypassNext();

        const submitData = {
            ...data,
            client: {
                ...data.client,
                sex: clientGender,
                email: clientEmail,
                contact_number: clientContact,
            },
            consent,
            employment: {
                ...data.employment,
                country: lastCountry || data.employment.country,
                position: lastJob || data.employment.position,
                last_country: lastCountry,
                last_position: lastJob,
                date_of_arrival: arrivalDate,
            },
            ...(hasNextOfKin && {
                next_of_kin: {
                    ...data.next_of_kin,
                    first_name: nokFirstName,
                    last_name: nokLastName,
                    relationship: nokRelationship,
                    phone_number: nokContact,
                },
            }),
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
                onSuccess: () => { },
                onError: (errors) => {
                    console.error('Publish failed:', errors);
                },
                preserveScroll: true,
            });
            return;
        }

        const submitData = {
            ...data,
            client: {
                ...data.client,
                sex: clientGender,
                email: clientEmail,
                contact_number: clientContact,
            },
            consent,
            employment: {
                ...data.employment,
                country: lastCountry || data.employment.country,
                position: lastJob || data.employment.position,
                last_country: lastCountry,
                last_position: lastJob,
                date_of_arrival: arrivalDate,
            },
            ...(hasNextOfKin && {
                next_of_kin: {
                    ...data.next_of_kin,
                    first_name: nokFirstName,
                    last_name: nokLastName,
                    relationship: nokRelationship,
                    phone_number: nokContact,
                },
            }),
        };

        post(route('cases.store'), {
            data: submitData,
            onSuccess: () => { },
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
        setConsent(false);
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
                is_draft: false,
            },
            useState: {
                clientSource: 'new',
                nokFirstName: '', nokLastName: '', nokContact: '', nokRelationship: '',
                clientGender: 'Male', clientEmail: '', clientContact: '',
                lastCountry: '', lastJob: '', arrivalDate: '',
                hasNextOfKin: true, consent: false,
            },
        };
    }

    function handleSwitchToExisting() {
        setClientSource('existing');
    }

function handleConfirmClient(client) {
    if (!client) return;

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
        setLastCountry(emp.last_country || emp.country || '');
        setLastJob(emp.last_position || emp.position || '');
        setArrivalDate(emp.date_of_arrival || '');
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
        setNokFirstName(nok.first_name || '');
        setNokLastName(nok.last_name || '');
        setNokContact(nok.phone_number || '');
        setNokRelationship(nok.relationship || '');
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

    setSelectedClient(null);
}

    const nokSummary = hasNextOfKin
        ? [nokFirstName, nokLastName].filter(Boolean).join(' ') || 'Not yet provided'
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
                                                        <Select value={clientGender} onChange={(e) => setClientGender(e.target.value)} options={[{ label: 'Male', value: 'Male' }, { label: 'Female', value: 'Female' }]} />
                                                    </Field>
                                                    <Field label="Email Address" required>
                                                        <Input type="email" value={clientEmail} onChange={(e) => setClientEmail(e.target.value)} />
                                                    </Field>
                                                    <div className="col-span-4 -mt-2">
                                                        <p className="flex items-center gap-1.5 text-[11px] text-amber-700">
                                                            <span className="material-symbols-outlined text-[14px]">info</span>
                                                            This email will be used to send case notifications and updates to the client.
                                                        </p>
                                                    </div>
                                                    <Field label="Contact Number">
                                                        <PhoneInput value={clientContact} onChange={(val) => setClientContact(val)} />
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
                                                            <PhoneInput value={nokContact} onChange={(val) => setNokContact(val)} />
                                                        </Field>
                                                        <Field label="Email">
                                                            <Input type="email" value={data.next_of_kin.email} onChange={(e) => setData('next_of_kin', { ...data.next_of_kin, email: e.target.value })} />
                                                        </Field>
                                                        <div className="md:col-span-2">
                                                            <Subsection title="Address">
                                                                <AddressDropdowns
                                                                    values={data.next_of_kin.nok_address}
                                                                    onChange={handleNokAddressChange}
                                                                />
                                                            </Subsection>
                                                        </div>
                                                    </div>
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
                                                    <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-700 focus:ring-amber-600" />
                                                    <span>I acknowledge and confirm client consent for data use in the system.</span>
                                                </label>
                                                {!consent && <p className="mt-3 text-[12px] font-medium text-amber-800">Required to create a case for a new client.</p>}
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
                                <button type="button" onClick={handleSaveDraft} disabled={processing}
                                    className="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-5 py-2.5 text-[13px] font-bold text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span className="material-symbols-outlined text-[18px]">save</span>
                                    {processing ? (existingDraft ? 'Updating...' : 'Saving...') : (existingDraft ? 'Update Draft' : 'Save as Draft')}
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
