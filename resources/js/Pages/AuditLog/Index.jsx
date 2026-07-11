import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { AuditTimeline } from '@/Components/AuditTimeline';
import { useCallback } from 'react';

export default function AuditLogIndex({ logs, availableActions, availableModules, availableModulesLabels, filterValues }) {
  const handleFilterChange = useCallback((filters) => {
    const url = new URL(window.location);
    const filterKeys = ['action', 'module', 'user_id', 'date_from', 'date_to', 'search', 'per_page', 'page'];
    filterKeys.forEach(k => url.searchParams.delete(k));
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        url.searchParams.set(key, value);
      }
    });
    router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['logs', 'filterValues'] });
  }, []);

  const handlePageChange = useCallback((page) => {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, { preserveState: true, preserveScroll: true, only: ['logs'] });
  }, []);

  const pagination = {
    total: logs.total,
    currentPage: logs.current_page,
    totalPages: logs.last_page,
    from: logs.from,
    to: logs.to,
    perPage: logs.per_page,
  };

  return (
    <AppLayout title="Audit Logs">
      <Head title="Audit Logs" />
      <div className="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div data-tour="audit-header" className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Audit Logs</h1>
          <p className="text-sm text-slate-500 mt-1">Track all system activities and changes.</p>
        </div>

        <div data-tour="audit-timeline">
          <AuditTimeline
            logs={logs.data}
            availableActions={availableActions ?? []}
            availableModules={availableModules ?? []}
            availableModulesLabels={availableModulesLabels ?? {}}
            filterValues={filterValues ?? {}}
            onFilterChange={handleFilterChange}
            pagination={pagination}
            onPageChange={handlePageChange}
          />
        </div>
      </div>
    </AppLayout>
  );
}
