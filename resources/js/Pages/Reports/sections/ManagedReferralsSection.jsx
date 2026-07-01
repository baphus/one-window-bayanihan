import { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import StatusBadge from '@/Components/ui/StatusBadge';
import { COLORS, pageHeadingStyles } from '@/Components/Reports/pageHeadingStyles';
import DateRangePicker, { formatDisplayDate } from '@/Components/Reports/DateRangePicker';
import { exportToCsv } from '@/utils/export/exportCsv';

export default function ManagedReferralsSection({ managedReferrals }) {
  const referralColumns = useMemo(() => [
    { key: 'case_file', title: 'TRACKING ID', render: (row) => <span className="text-[12px] font-bold" style={{ color: COLORS.primary }}>{row.case_file?.case_number || 'N/A'}</span> },
    { key: 'client', title: 'CLIENT', render: (row) => <span className="text-[12px] font-semibold text-slate-700">{row.case_file?.client ? `${row.case_file.client.first_name} ${row.case_file.client.last_name}` : 'N/A'}</span> },
    { key: 'agency', title: 'AGENCY', render: (row) => <span className="text-[12px] text-slate-700">{row.agency?.name || row.agcy_id || 'N/A'}</span> },
    { key: 'required_services', title: 'SERVICE', render: (row) => <span className="text-[12px] text-slate-700">{row.required_services || 'N/A'}</span> },
    { key: 'status', title: 'STATUS', render: (row) => (
      <StatusBadge status={row.status} showIcon={false} />
    )},
    { key: 'created_at', title: 'CREATED', render: (row) => <span className="text-[12px] text-slate-600">{formatDisplayDate(row.created_at?.slice(0, 10))}</span> },
    { key: 'id', title: '', render: (row) => <Link href={route('referrals.show', row.id)} className="text-[11px] font-bold" style={{ color: COLORS.primary }}>View</Link> },
  ], []);

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator?.total || 0,
      startIndex: paginator?.from || 0,
      endIndex: paginator?.to || 0,
      currentPage: paginator?.current_page || 1,
      totalPages: paginator?.last_page || 1,
      rowsPerPage: paginator?.per_page || 10,
      hideControlBar: true,
      hidePagination: false,
    };
  }

  if (!managedReferrals) return null;

  return (
    <section className="border bg-white shadow-sm" style={{ borderColor: COLORS.border }}>
      <div className="flex items-center justify-between border-b px-4 py-3" style={{ borderColor: COLORS.border }}>
        <h3 className={pageHeadingStyles.sectionTitle}>Referral Detail</h3>
        <button
          type="button"
          onClick={() => {
            const rows = (managedReferrals?.data || []).map((r) => ({
              caseNo: r.case_file?.case_number || '',
              client: r.case_file?.client ? `${r.case_file.client.first_name} ${r.case_file.client.last_name}` : '',
              agency: r.agency?.name || '',
              service: r.required_services || '',
              status: r.status,
              created: r.created_at,
            }));
            exportToCsv(
              rows,
              [
                { header: 'Case No', accessor: (r) => r.caseNo },
                { header: 'Client', accessor: (r) => r.client },
                { header: 'Agency', accessor: (r) => r.agency },
                { header: 'Service', accessor: (r) => r.service },
                { header: 'Status', accessor: (r) => r.status },
                { header: 'Created', accessor: (r) => r.created },
              ],
              'referral-detail.csv',
            );
          }}
          className="inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.11em] text-slate-500 hover:text-slate-700"
        >
          <Download className="h-3.5 w-3.5" />
          Export CSV
        </button>
      </div>
      <UnifiedTable
        variant="embedded"
        data={managedReferrals?.data || []}
        columns={referralColumns}
        keyExtractor={(row) => row.id}
        {...paginatorProps(managedReferrals)}
        searchPlaceholder="Search case no, client, agency, service..."
      />
    </section>
  );
}
