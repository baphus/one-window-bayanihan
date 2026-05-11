import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

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

    const { data, setData, patch, post, processing, errors } = useForm({
        status: '',
        decision: '',
    });

    const milestoneForm = useForm({
        title: '',
        description: '',
    });

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

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Referral Detail
                    </h2>
                    <Link
                        href={route('referrals.index')}
                        className="text-sm text-indigo-600 hover:text-indigo-900"
                    >
                        &larr; Back to Referrals
                    </Link>
                </div>
            }
        >
            <Head title="Referral Detail" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="space-y-6">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">Referral Information</h3>
                            </div>
                            <div className="px-6 py-4">
                                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Status</dt>
                                        <dd className="mt-1">
                                            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[referral.status] || 'bg-gray-100 text-gray-800'}`}>
                                                {referral.status}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Agency</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{referral.agency?.name ?? 'N/A'}</dd>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <dt className="text-sm font-medium text-gray-500">Required Services</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{referral.required_services}</dd>
                                    </div>
                                    {referral.notes && (
                                        <div className="sm:col-span-2">
                                            <dt className="text-sm font-medium text-gray-500">Notes</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{referral.notes}</dd>
                                        </div>
                                    )}
                                    {referral.decision && (
                                        <div className="sm:col-span-2">
                                            <dt className="text-sm font-medium text-gray-500">Decision / Resolution</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{referral.decision}</dd>
                                        </div>
                                    )}
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Case Number</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            <Link href={route('cases.show', referral.case_id)} className="text-indigo-600 hover:text-indigo-900">
                                                {referral.case_file?.case_number ?? 'N/A'}
                                            </Link>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Client</dt>
                                        <dd className="mt-1 text-sm text-gray-900">
                                            {referral.case_file?.client
                                                ? `${referral.case_file.client.first_name} ${referral.case_file.client.last_name}`
                                                : 'N/A'}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        {canUpdateStatus && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Update Status</h3>
                                </div>
                                <div className="px-6 py-4">
                                    <form onSubmit={handleStatusUpdate}>
                                        <div className="mb-4">
                                            <InputLabel htmlFor="status" value="New Status" />
                                            <select
                                                id="status"
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                        <div className="mb-4">
                                            <InputLabel htmlFor="decision" value="Decision / Remarks" />
                                            <textarea
                                                id="decision"
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                rows={3}
                                                value={data.decision}
                                                onChange={(e) => setData('decision', e.target.value)}
                                            />
                                            <InputError message={errors.decision} className="mt-2" />
                                        </div>
                                        <PrimaryButton disabled={processing || !data.status}>
                                            Update Status
                                        </PrimaryButton>
                                    </form>
                                </div>
                            </div>
                        )}

                        {isAgency && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Add Milestone</h3>
                                </div>
                                <div className="px-6 py-4">
                                    <form onSubmit={handleMilestoneSubmit}>
                                        <div className="mb-4">
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
                                        <div className="mb-4">
                                            <InputLabel htmlFor="milestone_description" value="Description" />
                                            <textarea
                                                id="milestone_description"
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                </div>
                            </div>
                        )}

                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Milestones ({referral.milestones?.length ?? 0})
                                </h3>
                            </div>
                            <div className="px-6 py-4">
                                {(!referral.milestones || referral.milestones.length === 0) ? (
                                    <p className="text-sm text-gray-500">No milestones recorded yet.</p>
                                ) : (
                                    <ul className="space-y-4">
                                        {referral.milestones.map((ms) => (
                                            <li key={ms.id} className="border-l-2 border-indigo-200 pl-4">
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{ms.title}</p>
                                                        {ms.description && (
                                                            <p className="mt-1 text-sm text-gray-500">{ms.description}</p>
                                                        )}
                                                    </div>
                                                    <span className="text-xs text-gray-400">
                                                        {new Date(ms.created_at).toLocaleDateString()}
                                                        {ms.user ? ` by ${ms.user.name}` : ''}
                                                    </span>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>

                        {referral.attachments?.length > 0 && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h3 className="text-lg font-medium text-gray-900">Attachments</h3>
                                </div>
                                <div className="px-6 py-4">
                                    <ul className="divide-y divide-gray-200">
                                        {referral.attachments.map((att) => (
                                            <li key={att.id} className="flex items-center justify-between py-2">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{att.file_name}</p>
                                                    <p className="text-xs text-gray-500">
                                                        {att.uploader?.name ?? 'Unknown'} &middot; {new Date(att.created_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                <a
                                                    href={att.file_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-sm text-indigo-600 hover:text-indigo-900"
                                                >
                                                    View
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
