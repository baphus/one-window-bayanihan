import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';

const statusStyles = {
  OPEN: 'bg-green-100 text-green-800',
  CLOSED: 'bg-slate-100 text-slate-800',
};

export default function CaseIndex({ cases, filters }) {
    const { auth } = usePage().props;
    const canCreate = auth.user.role === 'CASE_MANAGER' || auth.user.role === 'ADMIN';

    function paginatorProps(paginator) {
      return {
        totalRecords: paginator.total,
        startIndex: paginator.from,
        endIndex: paginator.to,
        currentPage: paginator.current_page,
        totalPages: paginator.last_page,
        rowsPerPage: paginator.per_page,
        hideControlBar: true,
        onPageChange: (page) => {
          const url = new URL(window.location);
          url.searchParams.set('page', page);
          window.location = url.toString();
        },
        onRowsPerPageChange: (n) => {
          const url = new URL(window.location);
          url.searchParams.set('per_page', n);
          url.searchParams.delete('page');
          window.location = url.toString();
        },
      };
    }

    const columns = useMemo(() => [
      {
        key: 'case_number',
        title: 'Case #',
        sortable: true,
        render: (row) => row.case_number,
      },
      {
        key: 'tracker_number',
        title: 'Tracker #',
        sortable: true,
        render: (row) => row.tracker_number,
      },
      {
        key: 'client_type',
        title: 'Client Type',
        sortable: true,
        render: (row) => (row.client_type === 'OFW' ? 'OFW' : 'Next of Kin'),
      },
      {
        key: 'status',
        title: 'Status',
        sortable: true,
        render: (row) => (
          <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${statusStyles[row.status] || 'bg-slate-100 text-slate-800'}`}>
            {row.status}
          </span>
        ),
      },
      {
        key: 'created_at',
        title: 'Created',
        sortable: true,
        render: (row) => new Date(row.created_at).toLocaleDateString(),
      },
      {
        key: 'id',
        title: 'Actions',
        sortable: false,
        render: (row) => (
          <Link href={route('cases.show', row.id)} className="text-indigo-600 hover:text-indigo-900">
            View
          </Link>
        ),
      },
    ], []);

    return (
        <AppLayout title="Case Management">
            <Head title="Case Management" />

            <div className="mb-8">
              <div className="flex items-center justify-between">
                <div>
                  <h1 className="text-2xl font-bold text-slate-900">Cases</h1>
                  <p className="text-sm text-slate-500 mt-1">Manage all client cases.</p>
                </div>
                {canCreate && (
                    <Link href={route('cases.create')}>
                        <PrimaryButton>Create New Case</PrimaryButton>
                    </Link>
                )}
              </div>
            </div>

            <div className="space-y-4">
              <div className="max-w-md">
                <TextInput
                    type="text"
                    placeholder="Search by case number, tracker number..."
                    className="w-full"
                    defaultValue={filters?.search ?? ''}
                    onInput={(e) => {
                        const url = new URL(window.location);
                        if (e.target.value) {
                            url.searchParams.set('search', e.target.value);
                        } else {
                            url.searchParams.delete('search');
                        }
                        window.location = url.toString();
                    }}
                />
              </div>

              <UnifiedTable
                columns={columns}
                data={cases.data}
                keyExtractor={(row) => row.id}
                {...paginatorProps(cases)}
              />
            </div>
        </AppLayout>
    );
}
