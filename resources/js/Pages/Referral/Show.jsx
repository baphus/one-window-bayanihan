import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useRef, useMemo } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Timeline from '@/Components/Timeline';
import { CardSection, MetaTile, InfoCell, SubsectionCard } from '@/Components/ui/CardSection';
import { formatDisplayDate } from '@/lib/utils';

const statusStyles = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

const statusTransitions = {
    PENDING: ['PROCESSING', 'FOR_COMPLIANCE', 'REJECTED'],
    PROCESSING: ['FOR_COMPLIANCE', 'COMPLETED', 'REJECTED'],
    FOR_COMPLIANCE: ['PROCESSING', 'COMPLETED', 'REJECTED'],
    COMPLETED: [],
    REJECTED: [],
};

export default function ReferralShow({ referral }) {
    const { auth } = usePage().props;
    const isAgency = auth.user.role === 'AGENCY';
    const isCaseManager = auth.user.role === 'CASE_MANAGER';

    const { data, setData, patch, processing, errors } = useForm({
        status: '',
        decision: '',
        decision_comment: '',
    });

    const milestoneForm = useForm({
        title: '',
        description: '',
    });

    const statusInitialRef = useRef({ status: '', decision: '', decision_comment: '' });
    const milestoneInitialRef = useRef({ title: '', description: '' });
    const hasDirty = useMemo(() => (
        data.status !== statusInitialRef.current.status
        || data.decision !== statusInitialRef.current.decision
        || data.decision_comment !== statusInitialRef.current.decision_comment
        || milestoneForm.data.title !== milestoneInitialRef.current.title
        || milestoneForm.data.description !== milestoneInitialRef.current.description
    ), [data, milestoneForm.data]);
    const { showModal, confirmNavigation, cancelNavigation } = useUnsavedChanges(hasDirty);

    function handleStatusUpdate(e) {
        e.preventDefault();
        patch(route('referrals.update-status', referral.id));
    }

    function handleMilestoneSubmit(e) {
        e.preventDefault();
        milestoneForm.post(route('referrals.milestones.store', referral.id), {
            preserveScroll: true,
            onSuccess: () => milestoneForm.reset(),
        });
    }

    const allowedTransitions = statusTransitions[referral.status] || [];
    const canUpdateStatus = allowedTransitions.length > 0 && (isAgency || isCaseManager);

    const timelineItems = (referral.milestones || []).map((ms) => ({
        id: ms.id,
        title: ms.title,
        description: ms.description,
        date: ms.created_at,
        meta: ms.user ? `by ${ms.user.name}` : '',
    }));

    const statusDotColor = (referral.status === 'PENDING' || referral.status === 'PROCESSING' || referral.status === 'FOR_COMPLIANCE')
        ? referral.status : 'default';

    return (
        <AppLayout title="Referral Detail">
            <Head title="Referral Detail" />

            <div className="mb-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Referral Detail</h1>
                        <p className="text-sm text-slate-500 mt-1">View and manage referral progress.</p>
                    </div>
                    <Link
                        href={route('referrals.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        &larr; Back to Referrals
                    </Link>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <CardSection title="Referral Information">
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8] border-b border-[#d8dee8]">
                            <InfoCell label="Status" value={
                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[referral.status] || 'bg-slate-100 text-slate-800'}`}>
                                    {referral.status}
                                </span>
                            } />
                            <InfoCell label="Agency" value={referral.agency?.name ?? 'N/A'} />
                            <InfoCell label="Case Number" value={
                                <Link href={route('cases.show', referral.case_id)} className="text-indigo-600 hover:text-indigo-900">
                                    {referral.case_file?.case_number ?? 'N/A'}
                                </Link>
                            } />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[#d8dee8]">
                            <InfoCell label="Client" value={
                                referral.case_file?.client
                                    ? `${referral.case_file.client.first_name} ${referral.case_file.client.last_name}`
                                    : 'N/A'
                            } />
                            <InfoCell label="Date Referred" value={formatDisplayDate(referral.created_at)} />
                            <InfoCell label="Last Updated" value={formatDisplayDate(referral.updated_at)} />
                        </div>
                        {referral.required_services && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Required Services</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.required_services}</p>
                            </div>
                        )}
                        {referral.notes && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Notes</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.notes}</p>
                            </div>
                        )}
                        {referral.decision && (
                            <div className="px-3 py-2 border-b border-[#d8dee8]">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Decision</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.decision}</p>
                            </div>
                        )}
                        {referral.decision_comment && (
                            <div className="px-3 py-2">
                                <p className="text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-500">Decision Comment</p>
                                <p className="mt-1 text-[12px] font-semibold text-slate-700">{referral.decision_comment}</p>
                            </div>
                        )}
                    </CardSection>

                    {canUpdateStatus && (
                        <CardSection title="Update Status">
                            <form onSubmit={handleStatusUpdate} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="status" value="New Status" />
                                    <select
                                        id="status"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                    >
                                        <option value="">Select status...</option>
                                        {allowedTransitions.map((s) => (
                                            <option key={s} value={s}>{s}</option>
                                        ))}
                                    </select>
                                    <InputError message={errors.status} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="decision" value="Decision" />
                                    <select
                                        id="decision"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.decision}
                                        onChange={(e) => setData('decision', e.target.value)}
                                    >
                                        <option value="">Select decision...</option>
                                        <option value="ACCEPT">Accept</option>
                                        <option value="REJECT">Reject</option>
                                    </select>
                                    <InputError message={errors.decision} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="decision_comment" value="Decision Comment" />
                                    <textarea
                                        id="decision_comment"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={3}
                                        value={data.decision_comment}
                                        onChange={(e) => setData('decision_comment', e.target.value)}
                                    />
                                    <InputError message={errors.decision_comment} className="mt-2" />
                                </div>
                                <PrimaryButton disabled={processing || !data.status}>
                                    Update Status
                                </PrimaryButton>
                            </form>
                        </CardSection>
                    )}

                    {referral.attachments?.length > 0 && (
                        <CardSection title={`Attachments (${referral.attachments.length})`}>
                            <div className="divide-y divide-[#d8dee8]">
                                {referral.attachments.map((att) => (
                                    <div key={att.id} className="flex items-center justify-between py-2.5 px-1">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <span className="material-symbols-outlined text-slate-400 text-[20px] shrink-0">description</span>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-slate-900 truncate">{att.file_name}</p>
                                                <p className="text-xs text-slate-500">
                                                    {att.user?.name ?? 'Unknown'} &middot; {formatDisplayDate(att.created_at)}
                                                    {att.size ? ` \u00b7 ${(att.size / 1024).toFixed(1)} KB` : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <a
                                            href={att.file_path}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-sm text-indigo-600 hover:text-indigo-900 shrink-0 ml-2"
                                        >
                                            View
                                        </a>
                                    </div>
                                ))}
                            </div>
                        </CardSection>
                    )}

                    {isAgency && (
                        <CardSection title="Add Milestone">
                            <form onSubmit={handleMilestoneSubmit} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="milestone_title" value="Title *" />
                                    <TextInput
                                        id="milestone_title"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={milestoneForm.data.title}
                                        onChange={(e) => milestoneForm.setData('title', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.title} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="milestone_description" value="Description" />
                                    <textarea
                                        id="milestone_description"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={2}
                                        value={milestoneForm.data.description}
                                        onChange={(e) => milestoneForm.setData('description', e.target.value)}
                                    />
                                    <InputError message={milestoneForm.errors.description} className="mt-2" />
                                </div>
                                <PrimaryButton disabled={milestoneForm.processing || !milestoneForm.data.title}>
                                    Add Milestone
                                </PrimaryButton>
                            </form>
                        </CardSection>
                    )}
                </div>

                <div className="space-y-6">
                    <CardSection title="Overview">
                        <div className="space-y-2">
                            <MetaTile label="Status" value={
                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[referral.status] || 'bg-slate-100 text-slate-800'}`}>
                                    {referral.status}
                                </span>
                            } />
                            <MetaTile label="Agency" value={referral.agency?.name ?? 'N/A'} />
                            <MetaTile label="Case No." value={
                                <Link href={route('cases.show', referral.case_id)} className="text-indigo-600 hover:text-indigo-900">
                                    {referral.case_file?.case_number ?? 'N/A'}
                                </Link>
                            } />
                            <MetaTile label="Client" value={
                                referral.case_file?.client
                                    ? `${referral.case_file.client.first_name} ${referral.case_file.client.last_name}`
                                    : 'N/A'
                            } />
                            <MetaTile label="Referred" value={formatDisplayDate(referral.created_at)} />
                            <MetaTile label="Updated" value={formatDisplayDate(referral.updated_at)} />
                        </div>
                    </CardSection>

                    <CardSection title={`Timeline (${(referral.milestones || []).length})`}>
                        <Timeline items={timelineItems} dotColorKey={statusDotColor} />
                    </CardSection>
                </div>
            </div>
            <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
        </AppLayout>
    );
}
