import { useMemo, useState } from 'react';
import AddressDropdowns from '@/Components/AddressDropdowns';
import CountrySelect from '@/Components/CountrySelect';
import InputError from '@/Components/InputError';
import Select from '@/Components/Select';
import { addressCodesToNames } from '@/data/philippine-addresses';
import safeRoute from '@/utils/safeRoute';

export const CIVIL_STATUS_LABELS = {
    SINGLE: 'Single',
    MARRIED: 'Married',
    WIDOWED: 'Widowed',
    SEPARATED: 'Separated',
    ANNULLED: 'Annulled',
    OTHER: 'Other',
};

export const DEPLOYMENT_MODE_LABELS = {
    AGENCY_HIRED: 'Agency hired',
    DIRECT_HIRED: 'Direct hired',
    TOURIST_VISIT: 'Tourist / visit',
    BUSINESS: 'Business',
    INVITATION: 'Invitation',
};

export const EMPLOYMENT_STATUS_LABELS = {
    ON_SITE: 'On-site / currently abroad',
    FOR_DEPLOYMENT: 'For deployment (pre-departure)',
    RETURNED: 'Returned (reintegration)',
};

export const ASSESSMENT_LABELS = {
    DISTRESSED_OFW: 'Distressed OFW',
    MEDICALLY_REPATRIATED: 'Medically repatriated',
    BURIAL_ASSISTANCE_CONCERN: 'Burial assistance concern',
    POSSIBLE_LOCAL_EMPLOYMENT: 'Possible local employment',
    PSYCHOSOCIAL_MEDICAL_INTERVENTION: 'Psychosocial / medical intervention',
    SKILLS_ENHANCEMENT_TRAINING: 'Skills enhancement training',
    LEGAL_ASSISTANCE: 'Legal assistance',
    OTHER: 'Other',
};

export const RETURN_REASON_LABELS = {
    DISTRESSED_WORKER: 'Distressed worker',
    TERMINATION: 'Termination',
    OTHERS: 'Others',
};

export const DOCUMENT_SLOT_LABELS = {
    passport_front_page: 'Passport front page',
    arrival_stamp: 'BI arrival stamp',
    employment_contract: 'Employment contract abroad',
    oec: 'Overseas Employment Certificate (OEC)',
    work_visa: 'Work visa',
    distress_certificate: 'Distress certificate',
    termination_letter: 'Termination letter',
    sworn_affidavit: 'Notarized sworn affidavit stating the reason for return',
    employment_evidence: 'Employment evidence (contract, OEC, or work visa)',
};

export const EMPLOYMENT_EVIDENCE_SLOTS = ['employment_contract', 'oec', 'work_visa'];

export function requiredReasonSlot(returnReason) {
    if (returnReason === 'DISTRESSED_WORKER') return 'distress_certificate';
    if (returnReason === 'TERMINATION') return 'termination_letter';
    return 'sworn_affidavit';
}

export function emptyIntakeData({ clientType = 'OFW', consentTextVersion = 'case-intake-v1', intakeDate = '', email = '' } = {}) {
    return {
        client_type: clientType,
        intake_date: intakeDate,
        first_name: '',
        middle_name: '',
        last_name: '',
        suffix: '',
        date_of_birth: '',
        sex: '',
        email,
        contact_number: '',
        civil_status: '',
        civil_status_other: '',
        deployment_mode: '',
        employment_status: '',
        address: { region: '', province: '', city_municipality: '', barangay: '', street: '' },
        employment_employer_name: '',
        employment_employer_address: '',
        employment_position: '',
        employment_country: '',
        employment_last_position: '',
        employment_date_of_arrival: '',
        vulnerability_indicator: 'None',
        summary: '',
        proposed_assessments: [],
        proposed_assessment_other: '',
        return_reason: '',
        documents: {},
        consent_text_version: consentTextVersion,
        consent: false,
        complied_onsite_slots: [],
        staff_summary: '',
        nok_name: '',
        nok_relationship: '',
        nok_contact_number: '',
        nok_email: '',
        nok_vulnerability_indicator: 'None',
        missing_document_reasons: {},
    };
}

function withAddressParts(data) {
    const names = addressCodesToNames(data.address);
    return {
        address_region: names.region,
        address_province: names.province,
        address_city_municipality: names.city_municipality,
        address_barangay: names.barangay,
        address_street: data.address.street,
        address_region_code: data.address.region,
        address_province_code: data.address.province,
        address_city_municipality_code: data.address.city_municipality,
        address_barangay_code: data.address.barangay,
    };
}

/** Payload shared by the public intake and staff case form. */
export function buildIntakePayload(data, mode) {
    const payload = {
        client_type: data.client_type,
        intake_date: data.intake_date,
        first_name: data.first_name.trim(),
        middle_name: data.middle_name.trim(),
        last_name: data.last_name.trim(),
        suffix: data.suffix,
        date_of_birth: data.date_of_birth,
        sex: data.sex,
        email: data.email.trim(),
        contact_number: data.contact_number.trim(),
        civil_status: data.civil_status,
        civil_status_other: data.civil_status === 'OTHER' ? data.civil_status_other.trim() : '',
        deployment_mode: data.deployment_mode,
        employment_status: data.employment_status,
        ...withAddressParts(data),
        employment_employer_name: data.employment_employer_name.trim(),
        employment_employer_address: data.employment_employer_address.trim(),
        employment_position: data.employment_position.trim(),
        employment_country: data.employment_country,
        employment_last_position: data.employment_last_position.trim(),
        employment_date_of_arrival: data.employment_date_of_arrival,
        vulnerability_indicator: data.vulnerability_indicator || 'None',
        summary: data.summary.trim(),
        proposed_assessments: data.proposed_assessments,
        proposed_assessment_other: data.proposed_assessments.includes('OTHER') ? data.proposed_assessment_other.trim() : '',
        return_reason: data.return_reason,
        consent_text_version: data.consent_text_version,
        documents: Object.fromEntries(Object.entries(data.documents ?? {}).filter(([, file]) => file)),
    };

    if (mode === 'public') {
        payload.consent = data.consent;
    } else {
        payload.consent = data.consent;
        payload.complied_onsite_slots = data.complied_onsite_slots ?? [];
        payload.staff_summary = data.staff_summary.trim();
        payload.nok_vulnerability_indicator = data.nok_vulnerability_indicator || 'None';
        payload.missing_document_reasons = Object.fromEntries(
            Object.entries(data.missing_document_reasons ?? {}).filter(([, reason]) => reason.trim()),
        );
        if (data.client_type === 'NEXT_OF_KIN') {
            payload.nok_name = data.nok_name.trim();
            payload.nok_relationship = data.nok_relationship.trim();
            payload.nok_contact_number = data.nok_contact_number.trim();
            payload.nok_email = data.nok_email.trim();
        }
    }

    return payload;
}

function Section({ title, description, children }) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 className="font-headline text-[15px] font-extrabold tracking-tight text-slate-900">{title}</h2>
            {description && <p className="mt-1 text-[12px] leading-relaxed text-slate-500">{description}</p>}
            <div className="mt-4 space-y-4">{children}</div>
        </section>
    );
}

function Field({ label, htmlFor, required, children, error, hint }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-600">
                {label}{required ? ' *' : ''}
            </label>
            {children}
            {hint && <p className="mt-1 text-[11px] text-slate-500">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}

const INPUT = 'h-10 w-full rounded-lg border border-slate-200 px-3 text-[13px] text-slate-800 outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary';
const TEXTAREA = 'w-full rounded-lg border border-slate-200 px-3 py-2.5 text-[13px] text-slate-800 outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary';

export default function CaseIntakeForm({ form, mode = 'public', options = {}, onSubmit, submitLabel = 'Submit case', disabled = false }) {
    const { data, setData, errors } = form;
    const {
        vulnerabilityOptions = [],
        assessmentOptions = Object.keys(ASSESSMENT_LABELS),
        civilStatuses = Object.keys(CIVIL_STATUS_LABELS),
        deploymentModes = Object.keys(DEPLOYMENT_MODE_LABELS),
        employmentStatuses = Object.keys(EMPLOYMENT_STATUS_LABELS),
        returnReasons = Object.keys(RETURN_REASON_LABELS),
        occupationOptions = [],
    } = options;
    const isStaff = mode === 'staff';

    const vulnerabilities = (vulnerabilityOptions.length ? vulnerabilityOptions : ['PWD', 'Senior Citizen', 'Solo Parent', 'Indigenous Person'])
        .map((option) => (typeof option === 'string' ? option : option?.value ?? option?.label))
        .filter((value) => value && value !== 'None');
    const selectedVulnerabilities = data.vulnerability_indicator && data.vulnerability_indicator !== 'None'
        ? data.vulnerability_indicator.split(',').map((value) => value.trim()).filter(Boolean)
        : [];
    const selectedNokVulnerabilities = data.nok_vulnerability_indicator && data.nok_vulnerability_indicator !== 'None'
        ? data.nok_vulnerability_indicator.split(',').map((value) => value.trim()).filter(Boolean)
        : [];

    const ageAtIntake = useMemo(() => {
        if (!data.date_of_birth || !data.intake_date) return null;
        const dob = new Date(`${data.date_of_birth}T00:00:00`);
        const intake = new Date(`${data.intake_date}T00:00:00`);
        if (Number.isNaN(dob.getTime()) || Number.isNaN(intake.getTime()) || intake < dob) return null;
        let age = intake.getFullYear() - dob.getFullYear();
        const beforeBirthday = intake.getMonth() < dob.getMonth()
            || (intake.getMonth() === dob.getMonth() && intake.getDate() < dob.getDate());
        if (beforeBirthday) age -= 1;
        return age;
    }, [data.date_of_birth, data.intake_date]);

    const employmentEvidenceSelected = EMPLOYMENT_EVIDENCE_SLOTS.some((slot) => Boolean(data.documents?.[slot]));
    const reasonSlot = data.return_reason ? requiredReasonSlot(data.return_reason) : null;

    const [matchedOfw, setMatchedOfw] = useState(null);

    async function lookupOfw(email) {
        if (!isStaff || !email) {
            setMatchedOfw(null);
            return;
        }
        try {
            const response = await fetch(safeRoute('cases.ofw-lookup', { email }, `/cases/ofw-lookup?email=${encodeURIComponent(email)}`), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                setMatchedOfw(null);
                return;
            }
            const body = await response.json();
            setMatchedOfw(body.name ?? '');
        } catch {
            setMatchedOfw(null);
        }
    }

    function setDocument(slot, file) {
        setData('documents', { ...(data.documents ?? {}), [slot]: file ?? null });
    }

    function toggleAssessment(value) {
        const next = data.proposed_assessments.includes(value)
            ? data.proposed_assessments.filter((item) => item !== value)
            : [...data.proposed_assessments, value];
        setData('proposed_assessments', next);
    }

    function toggleVulnerability(value, field = 'vulnerability_indicator', selected = selectedVulnerabilities) {
        const next = selected.includes(value)
            ? selected.filter((item) => item !== value)
            : [...selected, value];
        setData(field, next.length ? next.join(', ') : 'None');
    }

    function toggleOnsite(key) {
        const current = data.complied_onsite_slots ?? [];
        setData('complied_onsite_slots', current.includes(key)
            ? current.filter((item) => item !== key)
            : [...current, key]);
    }

    function onsiteCheckbox(key, label) {
        return (
            <label className="mt-2 flex items-center gap-2 text-[11px] font-semibold text-emerald-800">
                <input
                    type="checkbox"
                    checked={(data.complied_onsite_slots ?? []).includes(key)}
                    onChange={() => toggleOnsite(key)}
                    className="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/40"
                />
                {label}
            </label>
        );
    }

    function renderDocumentInput(slot, required) {
        const file = data.documents?.[slot];
        const missingReason = data.missing_document_reasons?.[slot] ?? '';
        return (
            <div key={slot} className="rounded-lg border border-slate-200 p-3">
                <label htmlFor={`intake-doc-${slot}`} className="block text-[12px] font-bold text-slate-700">
                    {DOCUMENT_SLOT_LABELS[slot]}{!isStaff && required ? <span className="text-red-600"> *</span> : null}
                </label>
                <input
                    id={`intake-doc-${slot}`}
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    onChange={(event) => setDocument(slot, event.target.files?.[0] ?? null)}
                    className="mt-2 block w-full text-[12px] text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-[12px] file:font-bold file:text-slate-700"
                />
                {file && <p className="mt-1 truncate text-[11px] text-emerald-700">{file.name}</p>}
                <InputError message={errors[`documents.${slot}`]} className="mt-1" />
                {isStaff && !file && onsiteCheckbox(slot, 'Complied onsite')}
                {isStaff && !file && (
                    <div className="mt-2">
                        <label htmlFor={`intake-missing-${slot}`} className="block text-[11px] font-semibold text-slate-600">
                            Note (optional)
                        </label>
                        <input
                            id={`intake-missing-${slot}`}
                            type="text"
                            maxLength={1000}
                            value={missingReason}
                            onChange={(event) => setData('missing_document_reasons', {
                                ...(data.missing_document_reasons ?? {}),
                                [slot]: event.target.value,
                            })}
                            placeholder="Anything the case manager needs to remember about this document."
                            className={`${INPUT} mt-1`}
                        />
                        <InputError message={errors[`missing_document_reasons.${slot}`]} className="mt-1" />
                    </div>
                )}
            </div>
        );
    }

    return (
        <form onSubmit={onSubmit} className="max-w-4xl space-y-5" noValidate>
            {isStaff && (
                <Section title="Case filer" description="Choose who this case is for. A next-of-kin case still describes the affected OFW in the main fields below.">
                    <div className="flex flex-wrap gap-3" role="radiogroup" aria-label="Case filed for">
                        {[['OFW', 'OFW (self)'], ['NEXT_OF_KIN', 'Next of kin (case manager only)']].map(([value, label]) => (
                            <label key={value} className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-[13px] font-semibold ${data.client_type === value ? 'border-primary bg-primary/5 text-primary' : 'border-slate-200 text-slate-700'}`}>
                                <input type="radio" name="client_type" value={value} checked={data.client_type === value} onChange={() => setData('client_type', value)} className="h-4 w-4 text-primary focus:ring-primary/40" />
                                {label}
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.client_type} />
                </Section>
            )}

            <Section title="OFW identity" description="These details belong to the affected OFW and are saved on this case only.">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="First name" htmlFor="intake-first-name" required error={errors.first_name}>
                        <input id="intake-first-name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className={INPUT} autoComplete="given-name" maxLength={255} />
                    </Field>
                    <Field label="Last name" htmlFor="intake-last-name" required error={errors.last_name}>
                        <input id="intake-last-name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className={INPUT} autoComplete="family-name" maxLength={255} />
                    </Field>
                    <Field label="Middle name" htmlFor="intake-middle-name" error={errors.middle_name}>
                        <input id="intake-middle-name" value={data.middle_name} onChange={(e) => setData('middle_name', e.target.value)} className={INPUT} maxLength={255} />
                    </Field>
                    <Field label="Suffix" htmlFor="intake-suffix" error={errors.suffix}>
                        <Select id="intake-suffix" value={data.suffix} onChange={(e) => setData('suffix', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                            <option value="">None</option>
                            {['Jr', 'Sr', 'II', 'III', 'IV', 'V'].map((suffix) => <option key={suffix} value={suffix}>{suffix}</option>)}
                        </Select>
                    </Field>
                    <Field label="Date of birth" htmlFor="intake-dob" required error={errors.date_of_birth} hint={ageAtIntake !== null ? `Age at intake: ${ageAtIntake}` : 'Age is shown from the date of birth and intake date.'}>
                        <input id="intake-dob" type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} className={INPUT} max={data.intake_date || undefined} />
                    </Field>
                    <Field label="Sex" htmlFor="intake-sex" required error={errors.sex}>
                        <Select id="intake-sex" value={data.sex} onChange={(e) => setData('sex', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                            <option value="">Select…</option>
                            <option value="MALE">Male</option>
                            <option value="FEMALE">Female</option>
                        </Select>
                    </Field>
                    <Field label="Email" htmlFor="intake-email" required error={errors.email} hint={mode === 'public' ? 'Your registered account email. This cannot be changed here.' : undefined}>
                        <input id="intake-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} onBlur={(e) => lookupOfw(e.target.value.trim())} className={`${INPUT} ${mode === 'public' ? 'bg-slate-50 cursor-not-allowed' : ''}`} autoComplete="email" maxLength={255} readOnly={mode === 'public'} aria-readonly={mode === 'public'} />
                        {isStaff && matchedOfw !== null && (
                            <p className={`mt-1 text-[11px] ${matchedOfw ? 'text-emerald-700' : 'text-amber-700'}`}>
                                {matchedOfw ? `This case will be linked to the OFW account: ${matchedOfw}` : 'No active OFW account found. The case will be created unlinked and requirements stay staff-managed.'}
                            </p>
                        )}
                    </Field>
                    <Field label="Contact number" htmlFor="intake-contact" required error={errors.contact_number}>
                        <input id="intake-contact" type="tel" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} className={INPUT} autoComplete="tel" maxLength={50} />
                    </Field>
                    {isStaff && (
                        <Field label="Intake date" htmlFor="intake-date" required error={errors.intake_date} hint="Age at intake is fixed from this date.">
                            <input id="intake-date" type="date" value={data.intake_date} onChange={(e) => setData('intake_date', e.target.value)} className={INPUT} />
                        </Field>
                    )}
                </div>
            </Section>

            <Section title="Home address in the Philippines">
                <AddressDropdowns
                    values={data.address}
                    onChange={(field, value) => {
                        if (typeof field === 'object') {
                            setData('address', { ...data.address, ...field });
                        } else {
                            setData('address', { ...data.address, [field]: value });
                        }
                    }}
                    errors={{
                        region: errors.address_region,
                        province: errors.address_province,
                        city_municipality: errors.address_city_municipality,
                        barangay: errors.address_barangay,
                        street: errors.address_street,
                    }}
                />
            </Section>

            <Section title="Status and deployment">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Civil status" htmlFor="intake-civil-status" required error={errors.civil_status}>
                        <Select id="intake-civil-status" value={data.civil_status} onChange={(e) => setData('civil_status', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                            <option value="">Select…</option>
                            {civilStatuses.map((value) => <option key={value} value={value}>{CIVIL_STATUS_LABELS[value] ?? value}</option>)}
                        </Select>
                    </Field>
                    <Field label="Deployment mode" htmlFor="intake-deployment" required error={errors.deployment_mode}>
                        <Select id="intake-deployment" value={data.deployment_mode} onChange={(e) => setData('deployment_mode', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                            <option value="">Select…</option>
                            {deploymentModes.map((value) => <option key={value} value={value}>{DEPLOYMENT_MODE_LABELS[value] ?? value}</option>)}
                        </Select>
                    </Field>
                    <Field label="Employment status" htmlFor="intake-employment-status" required error={errors.employment_status}>
                        <Select id="intake-employment-status" value={data.employment_status} onChange={(e) => setData('employment_status', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                            <option value="">Select…</option>
                            {employmentStatuses.map((value) => <option key={value} value={value}>{EMPLOYMENT_STATUS_LABELS[value] ?? value}</option>)}
                        </Select>
                    </Field>
                </div>
                {data.civil_status === 'OTHER' && (
                    <Field label="Civil status details" htmlFor="intake-civil-other" required error={errors.civil_status_other}>
                        <input id="intake-civil-other" value={data.civil_status_other} onChange={(e) => setData('civil_status_other', e.target.value)} className={INPUT} maxLength={255} />
                    </Field>
                )}
            </Section>

            <Section title="Employment" description="Enter the employment this case is about. Jobsite country is separate from the employer address.">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Employer name" htmlFor="intake-employer" error={errors.employment_employer_name} hint="Leave blank if there is no employer yet.">
                        <input id="intake-employer" value={data.employment_employer_name} onChange={(e) => setData('employment_employer_name', e.target.value)} className={INPUT} maxLength={255} />
                    </Field>
                    <Field label="Position" htmlFor="intake-position" error={errors.employment_position}>
                        <input id="intake-position" value={data.employment_position} onChange={(e) => setData('employment_position', e.target.value)} className={INPUT} maxLength={255} />
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Employer address" htmlFor="intake-employer-address" error={errors.employment_employer_address}>
                            <input id="intake-employer-address" value={data.employment_employer_address} onChange={(e) => setData('employment_employer_address', e.target.value)} className={INPUT} maxLength={1000} />
                        </Field>
                    </div>
                    <Field label="Jobsite country or territory" htmlFor="intake-country" error={errors.employment_country}>
                        <CountrySelect value={data.employment_country} onChange={(value) => setData('employment_country', value)} placeholder="Select jobsite country" />
                    </Field>
                    <Field label="Last occupation" htmlFor="intake-last-position" error={errors.employment_last_position}>
                        <input id="intake-last-position" list="intake-occupations" value={data.employment_last_position} onChange={(e) => setData('employment_last_position', e.target.value)} className={INPUT} maxLength={255} />
                        <datalist id="intake-occupations">
                            {occupationOptions.map((option) => {
                                const value = typeof option === 'string' ? option : option?.value ?? option?.label;
                                return value ? <option key={value} value={value} /> : null;
                            })}
                        </datalist>
                    </Field>
                    <Field label="Date of arrival in the Philippines" htmlFor="intake-arrival" error={errors.employment_date_of_arrival}>
                        <input id="intake-arrival" type="date" value={data.employment_date_of_arrival} onChange={(e) => setData('employment_date_of_arrival', e.target.value)} min={data.date_of_birth || undefined} className={INPUT} />
                    </Field>
                </div>
            </Section>

            <Section title="Vulnerability">
                <p className="text-[12px] text-slate-600" id="intake-vulnerability-label">Select all that apply, or leave none selected.</p>
                <div className="flex flex-wrap gap-x-5 gap-y-2" role="group" aria-labelledby="intake-vulnerability-label">
                    {vulnerabilities.map((value) => (
                        <label key={value} className="inline-flex items-center gap-2 text-[13px] text-slate-700">
                            <input type="checkbox" checked={selectedVulnerabilities.includes(value)} onChange={() => toggleVulnerability(value)} className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40" />
                            {value}
                        </label>
                    ))}
                </div>
                <InputError message={errors.vulnerability_indicator} />
            </Section>

            {isStaff && data.client_type === 'NEXT_OF_KIN' && (
                <Section title="Next of kin" description="These relative details are stored on this case only, not as a reusable profile.">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Relative name" htmlFor="intake-nok-name" required error={errors.nok_name}>
                            <input id="intake-nok-name" value={data.nok_name} onChange={(e) => setData('nok_name', e.target.value)} className={INPUT} maxLength={255} />
                        </Field>
                        <Field label="Relationship" htmlFor="intake-nok-relationship" required error={errors.nok_relationship}>
                            <input id="intake-nok-relationship" value={data.nok_relationship} onChange={(e) => setData('nok_relationship', e.target.value)} className={INPUT} maxLength={255} />
                        </Field>
                        <Field label="Contact number" htmlFor="intake-nok-contact" required error={errors.nok_contact_number}>
                            <input id="intake-nok-contact" type="tel" value={data.nok_contact_number} onChange={(e) => setData('nok_contact_number', e.target.value)} className={INPUT} maxLength={255} />
                        </Field>
                        <Field label="Email" htmlFor="intake-nok-email" required error={errors.nok_email}>
                            <input id="intake-nok-email" type="email" value={data.nok_email} onChange={(e) => setData('nok_email', e.target.value)} className={INPUT} maxLength={255} />
                        </Field>
                    </div>
                    <div>
                        <p className="text-[12px] font-semibold text-slate-600">Relative vulnerability (optional)</p>
                        <div className="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                            {vulnerabilities.map((value) => (
                                <label key={value} className="inline-flex items-center gap-2 text-[13px] text-slate-700">
                                    <input type="checkbox" checked={selectedNokVulnerabilities.includes(value)} onChange={() => toggleVulnerability(value, 'nok_vulnerability_indicator', selectedNokVulnerabilities)} className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40" />
                                    {value}
                                </label>
                            ))}
                        </div>
                    </div>
                </Section>
            )}

            <Section title="Statement" description="Describe what happened and what help is needed.">
                <Field label="OFW statement" htmlFor="intake-summary" required error={errors.summary}>
                    <textarea id="intake-summary" rows={6} maxLength={5000} value={data.summary} onChange={(e) => setData('summary', e.target.value)} className={TEXTAREA} />
                </Field>
                {isStaff && (
                    <Field label="Staff summary" htmlFor="intake-staff-summary" error={errors.staff_summary} hint="Internal summary kept separate from the OFW statement.">
                        <textarea id="intake-staff-summary" rows={4} maxLength={5000} value={data.staff_summary} onChange={(e) => setData('staff_summary', e.target.value)} className={TEXTAREA} />
                    </Field>
                )}
            </Section>

            <Section title="Requested assistance" description="Select the assistance that may apply. Multiple choices are allowed.">
                <div className="grid gap-2 sm:grid-cols-2">
                    {assessmentOptions.map((value) => (
                        <label key={value} className="inline-flex items-center gap-2 text-[13px] text-slate-700">
                            <input type="checkbox" checked={data.proposed_assessments.includes(value)} onChange={() => toggleAssessment(value)} className="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40" />
                            {ASSESSMENT_LABELS[value] ?? value}
                        </label>
                    ))}
                </div>
                <InputError message={errors.proposed_assessments} />
                {data.proposed_assessments.includes('OTHER') && (
                    <Field label="Other assistance" htmlFor="intake-assessment-other" required error={errors.proposed_assessment_other}>
                        <input id="intake-assessment-other" value={data.proposed_assessment_other} onChange={(e) => setData('proposed_assessment_other', e.target.value)} className={INPUT} maxLength={2000} />
                    </Field>
                )}
            </Section>

            <Section title="Return reason and documents" description={isStaff ? 'Upload what the OFW brought. Leave the rest for compliance or tick that it was complied onsite.' : 'Passport front page, BI arrival stamp, one employment document and the reason evidence are required to submit.'}>
                <Field label="Reason for return" htmlFor="intake-return-reason" required error={errors.return_reason}>
                    <Select id="intake-return-reason" value={data.return_reason} onChange={(e) => setData('return_reason', e.target.value)} className="h-10 w-full px-3 text-[13px]">
                        <option value="">Select…</option>
                        {returnReasons.map((value) => <option key={value} value={value}>{RETURN_REASON_LABELS[value] ?? value}</option>)}
                    </Select>
                </Field>
                <div className="space-y-3">
                    {renderDocumentInput('passport_front_page', true)}
                    {renderDocumentInput('arrival_stamp', true)}
                    <div className="rounded-lg border border-dashed border-slate-300 p-3">
                        <p className="text-[12px] font-bold text-slate-700">Employment evidence {!isStaff && <span className="text-red-600">*</span>}</p>
                        <p className="mt-1 text-[11px] text-slate-500">Upload any one of these: employment contract, OEC, or work visa.</p>
                        <div className="mt-2 space-y-2">
                            {EMPLOYMENT_EVIDENCE_SLOTS.map((slot) => renderDocumentInput(slot, false))}
                        </div>
                        {!isStaff && !employmentEvidenceSelected && <p className="mt-2 text-[11px] text-amber-700">At least one employment document is required.</p>}
                        {isStaff && !employmentEvidenceSelected && onsiteCheckbox('employment_evidence', 'Employment evidence complied onsite')}
                    </div>
                    {reasonSlot && renderDocumentInput(reasonSlot, true)}
                </div>
            </Section>

            <Section title="Consent">
                <label htmlFor="intake-consent" className="flex items-start gap-3 text-[13px] leading-relaxed text-slate-700">
                    <input id="intake-consent" type="checkbox" checked={data.consent} onChange={(e) => setData('consent', e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/40" />
                    <span>
                        {isStaff
                            ? `The OFW consents to the collection, use, and sharing of these details and documents with authorised partner agencies to provide assistance and manage their case, under consent version ${data.consent_text_version}.`
                            : `I consent to the collection, use, and sharing of these details and documents with authorised partner agencies to provide assistance and manage my case, under consent version ${data.consent_text_version}.`} <span className="text-red-600">*</span>
                    </span>
                </label>
                <InputError message={errors.consent} />
            </Section>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-[11px] text-slate-500">Files are stored privately and only shared with authorised staff and agencies.</p>
                <button
                    type="submit"
                    disabled={disabled || form.processing}
                    className="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-primary px-6 text-[13px] font-bold text-white transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-60"
                >
                    {form.processing ? 'Submitting…' : submitLabel}
                </button>
            </div>
        </form>
    );
}
