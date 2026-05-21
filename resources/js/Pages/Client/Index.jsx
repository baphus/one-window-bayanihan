import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

export default function ClientIndex({ clients }) {
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
      key: 'name',
      title: 'Name',
      sortable: true,
      render: (row) =>
        [row.first_name, row.middle_name, row.last_name, row.suffix].filter(Boolean).join(' '),
      sortAccessor: (row) => `${row.last_name}, ${row.first_name}`,
    },
    { key: 'sex', title: 'Sex', sortable: true,
      render: (row) => row.sex || 'N/A',
    },
    {
      key: 'date_of_birth',
      title: 'Date of Birth',
      sortable: true,
      render: (row) =>
        row.date_of_birth ? new Date(row.date_of_birth).toLocaleDateString() : 'N/A',
    },
    {
      key: 'case_number',
      title: 'Case #',
      sortable: true,
      render: (row) =>
        row.case_file ? (
          <Link href={route('cases.show', row.case_file.id)} className="text-indigo-600 hover:text-indigo-900">
            {row.case_file.case_number}
          </Link>
        ) : 'N/A',
    },
    {
      key: 'referrals',
      title: 'Referrals',
      sortable: false,
      render: (row) => row.case_file?.referrals?.length ?? 0,
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <Link href={route('clients.show', row.id)} className="text-indigo-600 hover:text-indigo-900 font-medium">
          View Details
        </Link>
      ),
    },
  ], []);

  return (
    <AppLayout title="Clients">
      <Head title="Clients" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Clients</h1>
        <p className="text-sm text-slate-500 mt-1">View all registered clients and their associated cases.</p>
      </div>

      <UnifiedTable
        columns={columns}
        data={clients.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(clients)}
      />
    </AppLayout>
  );
}
