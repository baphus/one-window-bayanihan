import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

const statusStyles = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    PROCESSING: 'bg-blue-100 text-blue-800',
    FOR_COMPLIANCE: 'bg-orange-100 text-orange-800',
    COMPLETED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
};

export default function TrackingShow({ trackingId, trackedCase, caseOverview, caseTimeline, trackingAgencies }) {
    return (
        <GuestLayout>
            <Head title={`Case ${trackedCase.caseNo}`} />

            <div className="min-h-screen bg-gray-100 py-8">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 text-center">
                        <h1 className="text-2xl font-bold text-gray-900">Case Tracking</h1>
                        <p className="text-sm text-gray-500">Tracker: {trackingId}</p>
                    </div>

                    <div className="space-y-6">
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h2 className="text-lg font-medium text-gray-900">Case Overview</h2>
                            </div>
                            <div className="px-6 py-4">
                                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Case Number</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{trackedCase.caseNo}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Client</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{trackedCase.clientName}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Client Type</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{trackedCase.clientType}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-sm font-medium text-gray-500">Status</dt>
                                        <dd className="mt-1">
                                            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[trackedCase.status] || 'bg-gray-100 text-gray-800'}`}>
                                                {trackedCase.status}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>

                                {caseOverview.narrative && (
                                    <div className="mt-4">
                                        <dt className="text-sm font-medium text-gray-500">Narrative</dt>
                                        <dd className="mt-1 text-sm text-gray-900">{caseOverview.narrative}</dd>
                                    </div>
                                )}
                            </div>
                        </div>

                        {caseOverview.ofw && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h2 className="text-lg font-medium text-gray-900">OFW Profile</h2>
                                </div>
                                <div className="px-6 py-4">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Full Name</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.ofw.fullName}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Date of Birth</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.ofw.dateOfBirth || 'N/A'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Gender</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.ofw.gender || 'N/A'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Home Address</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.ofw.homeAddress || 'N/A'}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        )}

                        {caseOverview.workHistory && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h2 className="text-lg font-medium text-gray-900">Work History</h2>
                                </div>
                                <div className="px-6 py-4">
                                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Last Country</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.workHistory.lastCountry || 'N/A'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Last Position</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.workHistory.lastPosition || 'N/A'}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-sm font-medium text-gray-500">Arrival Date</dt>
                                            <dd className="mt-1 text-sm text-gray-900">{caseOverview.workHistory.arrivalDate || 'N/A'}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        )}

                        {trackingAgencies.length > 0 && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h2 className="text-lg font-medium text-gray-900">Referral Status by Agency</h2>
                                </div>
                                <div className="divide-y divide-gray-200 px-6 py-4">
                                    {trackingAgencies.map((agency, i) => (
                                        <div key={i} className="py-3 first:pt-0 last:pb-0">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{agency.name}</p>
                                                    {agency.latestMilestoneLabel && (
                                                        <p className="text-xs text-gray-500">{agency.latestMilestoneLabel}</p>
                                                    )}
                                                </div>
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[agency.status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {agency.status}
                                                </span>
                                            </div>
                                            {agency.steps && (
                                                <div className="mt-2 flex items-center gap-1">
                                                    {agency.steps.map((step, j) => (
                                                        <div key={j} className="flex items-center">
                                                            <span className={`flex h-5 w-5 items-center justify-center rounded-full text-xs ${
                                                                step.state === 'complete' ? 'bg-green-500 text-white' :
                                                                step.state === 'active' ? 'bg-blue-500 text-white' :
                                                                'bg-gray-200 text-gray-500'
                                                            }`}>
                                                                {step.state === 'complete' ? '✓' : j + 1}
                                                            </span>
                                                            {j < agency.steps.length - 1 && (
                                                                <div className={`mx-1 h-0.5 w-8 ${
                                                                    step.state === 'complete' ? 'bg-green-500' : 'bg-gray-200'
                                                                }`} />
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {caseTimeline.length > 0 && (
                            <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                                <div className="border-b border-gray-200 px-6 py-4">
                                    <h2 className="text-lg font-medium text-gray-900">Case Timeline</h2>
                                </div>
                                <div className="px-6 py-4">
                                    <div className="relative">
                                        <div className="absolute left-4 top-0 h-full w-0.5 bg-gray-200" />
                                        <ul className="space-y-6">
                                            {caseTimeline.map((item, i) => (
                                                <li key={i} className="relative pl-10">
                                                    <div className="absolute left-2.5 flex h-3 w-3 items-center justify-center rounded-full bg-indigo-500 ring-4 ring-white" />
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-900">{item.title}</p>
                                                        <p className="text-xs text-gray-500">
                                                            {item.agency} &middot; {new Date(item.date).toLocaleDateString()} {new Date(item.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                                        </p>
                                                        {item.detail && (
                                                            <p className="mt-1 text-sm text-gray-600">{item.detail}</p>
                                                        )}
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
