import { useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader } from '@/Components/Dashboard/primitives';
import CaseIntakeForm, { buildIntakePayload, emptyIntakeData } from '@/Components/CaseIntakeForm';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';

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
        vulnerabilityOptions = [],
        occupationOptions = [],
    } = props;

    const form = useForm(emptyIntakeData({ intakeDate: TODAY, consentTextVersion }));
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
        vulnerabilityOptions,
        occupationOptions,
    }), [consentTextVersion, documentSlots, returnReasons, civilStatuses, deploymentModes, employmentStatuses, assessmentOptions, vulnerabilityOptions, occupationOptions]);

    function submit(event) {
        event.preventDefault();
        if (form.processing) return;
        form.clearErrors();
        bypassNext();
        form.transform((data) => buildIntakePayload(data, 'staff'));
        form.post(safeRoute('cases.store', undefined, '/cases'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => toast.success('Case created successfully.'),
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error((Array.isArray(first) ? first[0] : first) || 'Please check the highlighted fields.');
            },
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
            {UnsavedModal}
        </AppLayout>
    );
}
