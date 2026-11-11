import { useMemo } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import OfwLayout from '@/Layouts/OfwLayout';
import CaseIntakeForm, { buildIntakePayload, emptyIntakeData } from '@/Components/CaseIntakeForm';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import { PageHeader } from '@/Components/Dashboard/primitives';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';

const TODAY = (() => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
})();

export default function Intake(props) {
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
        auth,
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
        form.transform((data) => {
            const payload = buildIntakePayload(data, 'public');
            // Auth OFWs may not override account identity: server records the owner.
            return { ...payload, intake_date: '', email: payload.email || auth?.user?.email || '' };
        });
        form.post(safeRoute('ofw.intake.store', undefined, '/my-cases/intake'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => toast.success('Request submitted. It is now pending review.'),
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error((Array.isArray(first) ? first[0] : first) || 'Please check the highlighted fields.');
            },
            onFinish: () => form.transform((data) => data),
        });
    }

    return (
        <OfwLayout title="Request Assistance">
            <Head title="Request Assistance" />
            <Link
                href={safeRoute('ofw.dashboard', undefined, '/my-cases')}
                className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"
            >
                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Back to My Requests
            </Link>

            <PageHeader
                title="Request Assistance"
                subtitle="A DMW Region VII case manager will review your request. Enter your details fresh ΓÇö nothing is copied from earlier requests or your account settings."
            />

            <CaseIntakeForm
                form={form}
                mode="public"
                options={options}
                onSubmit={submit}
                submitLabel="Submit Request"
            />
            {UnsavedModal}
        </OfwLayout>
    );
}
