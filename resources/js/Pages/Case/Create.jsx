import { useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader } from '@/Components/Dashboard/primitives';
import CaseIntakeForm, { buildIntakePayload, emptyIntakeData } from '@/Components/CaseIntakeForm';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';
import useSessionDraft from '@/Hooks/useSessionDraft';
import DraftControls from '@/Components/DraftControls';

const TODAY = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
})();

export default function CaseCreate(props) {
    const {
        consentTextVersion = 'case-intake-v1',
        documentSlots = [],
        returnReasons = [],
        civilStatuses = [],
        deploymentModes = [],
        employmentStatuses = [],
        assessmentOptions = [],
        categories = [],
        vulnerabilityOptions = [],
        occupationOptions = [],
    } = props;

    const defaults = useMemo(() => emptyIntakeData({ intakeDate: TODAY, consentTextVersion }), [consentTextVersion]);
    const form = useForm(defaults);
    const draftFields = useMemo(() => ['client_type', 'intake_date', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex', 'email', 'contact_number', 'civil_status', 'civil_status_other', 'deployment_mode', 'employment_status', 'address', 'employment_employer_name', 'employment_employer_address', 'employment_position', 'employment_country', 'employment_last_position', 'employment_date_of_arrival', 'vulnerability_indicator', 'summary', 'proposed_assessments', 'proposed_assessment_other', 'category_ids', 'confirmed_assessments', 'confirmed_assessment_other', 'return_reason', 'staff_summary', 'nok_name', 'nok_relationship', 'nok_contact_number', 'nok_email', 'nok_vulnerability_indicator'], []);
    const draft = useSessionDraft({ userId: props.auth?.user?.id, formKey: 'case-create', data: form.data, setData: form.setData, defaults, fields: draftFields, shapes: { address: { region: '', province: '', city_municipality: '', barangay: '', street: '' } } });
    const { UnsavedModal, bypassNext } = useUnsavedChanges(form.isDirty);
    const toast = useToast();
    const options = useMemo(() => ({
        consentTextVersion,
        documentSlots,
        returnReasons,
        civilStatuses,
        deploymentModes,
        employmentStatuses,
        assessmentOptions,
        categories,
        vulnerabilityOptions,
        occupationOptions,
    }), [consentTextVersion, documentSlots, returnReasons, civilStatuses, deploymentModes, employmentStatuses, assessmentOptions, categories, vulnerabilityOptions, occupationOptions]);

    function submit(event) {
        event.preventDefault();
        if (form.processing) return;
        form.clearErrors();
        bypassNext();
        form.transform((data) => buildIntakePayload(data, 'staff'));
        form.post(safeRoute('cases.store', undefined, '/cases'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { draft.onSuccess(); toast.success('Case created successfully.'); },
            onError: () => {},
            onFinish: () => form.transform((data) => data),
        });
    }

    return (
        <AppLayout title="Create a case">
            <Head title="Create a case" />
            <Link href={safeRoute('cases.index', undefined, '/cases')} className="inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900">
                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to cases
            </Link>
            <PageHeader
                title="Create a case"
                subtitle="Every case keeps its own details. Nothing is copied from a previous case or a shared profile."
            />
            <CaseIntakeForm
                form={form}
                mode="staff"
                options={options}
                onSubmit={submit}
                submitLabel="Create case"
            />
            <DraftControls status={draft.status} onClear={draft.clear} />
            {UnsavedModal}
        </AppLayout>
    );
}
