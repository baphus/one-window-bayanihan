import { useState, useCallback } from 'react';
import { Download, Loader2 } from 'lucide-react';
import { router } from '@inertiajs/react';

function slugify(str) {
  return String(str).toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
}

function convertToCsv(data, title) {
  if (!data || data.length === 0) return '';

  const headers = Object.keys(data[0]);
  const rows = data.map((row) =>
    headers
      .map((h) => {
        const val = row[h] ?? '';
        return `"${String(val).replace(/"/g, '""')}"`;
      })
      .join(','),
  );

  return [headers.join(','), ...rows].join('\n');
}

function triggerCsvDownload(csvContent, filename) {
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${slugify(filename)}-${new Date().toISOString().split('T')[0]}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export default function ExportButton({
  data = null,
  filename = 'export',
  type = 'csv',
  title = 'Export',
  chartType = '',
  filters = {},
  disabled = false,
}) {
  const [exporting, setExporting] = useState(false);

  const handleExport = useCallback(() => {
    if (!data || data.length === 0) return;

    if (type === 'csv') {
      const csvContent = convertToCsv(data, filename);
      if (csvContent) {
        triggerCsvDownload(csvContent, filename);
      }
      return;
    }

    if (type === 'pdf') {
      setExporting(true);
      router.post(
        '/insights/export/pdf',
        {
          chartType,
          data,
          filters,
          title: filename,
        },
        {
          preserveScroll: true,
          preserveState: true,
          onFinish: () => setExporting(false),
          onError: () => setExporting(false),
        },
      );
    }
  }, [data, filename, type, chartType, filters]);

  const isDisabled = disabled || !data || data.length === 0 || exporting;

  return (
    <button
      type="button"
      onClick={handleExport}
      disabled={isDisabled}
      className={`inline-flex items-center justify-center gap-1 rounded p-1 text-slate-400 hover:text-slate-600 ${
        isDisabled ? 'cursor-not-allowed opacity-50' : ''
      }`}
      title={type === 'csv' ? 'Export CSV' : 'Export PDF'}
    >
      {exporting ? (
        <Loader2 className="h-4 w-4 animate-spin" />
      ) : (
        <Download className="h-4 w-4" />
      )}
    </button>
  );
}
