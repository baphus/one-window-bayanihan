import { Head, Deferred } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import AgencyDashboard from './Agency';
import AdminDashboard from './Admin';
import CaseManagerDashboard from './CaseManager';

export default function Dashboard(props) {
    const { role, dashboard } = props;

    const renderContent = () => {
        if (role === 'AGENCY') {
            return <AgencyDashboard dashboard={dashboard} />;
        }

        if (role === 'ADMIN') {
            return <AdminDashboard dashboard={dashboard} />;
        }

        return <CaseManagerDashboard dashboard={dashboard} />;
    };

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />
            <Deferred data="dashboard">
                {renderContent()}
            </Deferred>
        </AppLayout>
    );
}
