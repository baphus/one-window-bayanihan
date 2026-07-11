import { Head, Deferred, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { DashboardSkeleton } from '@/Components/Dashboard/primitives';
import AdminDashboard from './Dashboard/Admin';
import AgencyDashboard from './Dashboard/Agency';
import CaseManagerDashboard from './Dashboard/CaseManager';

function DashboardContent({ role }) {
    const { auth, dashboard } = usePage().props;
    const resolvedRole = role ?? auth?.user?.role;
    const dashboardData = dashboard ?? {};

    if (resolvedRole === 'ADMIN') {
        return <AdminDashboard dashboard={dashboardData} />;
    }

    if (resolvedRole === 'AGENCY') {
        return <AgencyDashboard dashboard={dashboardData} />;
    }

    return <CaseManagerDashboard dashboard={dashboardData} />;
}

export default function Dashboard({ role }) {
    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <Deferred data="dashboard" fallback={<DashboardSkeleton />}>
                <DashboardContent role={role} />
            </Deferred>
        </AppLayout>
    );
}
