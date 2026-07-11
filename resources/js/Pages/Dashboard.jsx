import { Head, Deferred, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DashboardBanner from '@/Components/DashboardBanner';
import { DashboardSkeleton } from '@/Components/Dashboard/primitives';
import AdminDashboard from './Dashboard/Admin';
import AgencyDashboard from './Dashboard/Agency';
import CaseManagerDashboard from './Dashboard/CaseManager';
import TourPrototype from './__TourPrototype';

function DashboardContent({ role }) {
    const { dashboard } = usePage().props;

    if (role === 'ADMIN') {
        return <AdminDashboard dashboard={dashboard ?? {}} />;
    }

    if (role === 'AGENCY') {
        return <AgencyDashboard dashboard={dashboard ?? {}} />;
    }

    return <CaseManagerDashboard dashboard={dashboard ?? {}} />;
}

export default function Dashboard({ role }) {
    const showTourPrototype = typeof window !== 'undefined'
        && new URLSearchParams(window.location.search).get('__TOUR_PROTO__') === '1';

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl">
                <DashboardBanner />
            </div>
            {showTourPrototype && <TourPrototype />}
            <Deferred data="dashboard" fallback={<DashboardSkeleton />}>
                <DashboardContent role={role} />
            </Deferred>
        </AppLayout>
    );
}
