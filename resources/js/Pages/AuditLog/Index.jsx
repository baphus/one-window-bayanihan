import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { formatDisplayDateTime } from '@/lib/utils';

const actionStyles = {
  CREATE: 'bg-green-100 text-green-800',
  UPDATE: 'bg-blue-100 text-blue-800',
  DELETE: 'bg-red-100 text-red-800',
  VIEW: 'bg-purple-100 text-purple-800',
  LOGIN: 'bg-slate-100 text-slate-800',
  LOGOUT: 'bg-slate-100 text-slate-800',
};

export default function AuditLogIndex({ logs }) {
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
      key: 'timestamp',
      title: 'Timestamp',
      sortable: true,
      render: (row) => formatDisplayDateTime(row.timestamp),
    },
    {
      key: 'user',
      title: 'User',
      sortable: true,
      render: (row) => row.user?.name ?? 'System',
    },
    {
      key: 'action',
      title: 'Action',
      sortable: true,
      render: (row) => (
        <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${actionStyles[row.action] || 'bg-slate-100 text-slate-800'}`}>
          {row.action}
        </span>
      ),
    },
    {
      key: 'module',
      title: 'Module',
      sortable: true,
      render: (row) => row.module,
    },
  ], []);

  return (
    <AppLayout title="Audit Logs">
      <Head title="Audit Logs" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Audit Logs</h1>
        <p className="text-sm text-slate-500 mt-1">Track all system activities and changes.</p>
      </div>

      <UnifiedTable
        columns={columns}
        data={logs.data}
        keyExtractor={(row) => row.id}
        {...paginatorProps(logs)}
      />
    </AppLayout>
  );
}
