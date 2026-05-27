import { Download } from 'lucide-react';
import { exportToCsv } from '@/utils/export/exportCsv';

export default function ExportButton({
  rows,
  columns,
  fileName = 'report',
  label = 'Export',
}) {
  const handleExport = () => {
    exportToCsv(rows, columns, fileName);
  };

  return (
    <button
      type="button"
      onClick={handleExport}
      className="inline-flex h-9 items-center gap-2 border border-[#cbd5e1] bg-white px-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#0b5a8c]"
    >
      <Download className="h-3.5 w-3.5" />
      {label}
    </button>
  );
}
