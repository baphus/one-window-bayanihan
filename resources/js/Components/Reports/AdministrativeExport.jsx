import { useState } from 'react';

export default function AdministrativeExport({ agencyOptions = [], role }) {
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [agency, setAgency] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');

  async function download(event) {
    event.preventDefault();
    if (busy || !from || !to || from > to) return;
    setBusy(true);
    setMessage('');
    try {
      const response = await fetch(route('admin.exports.administrative', { from, to, ...(agency ? { agency_id: agency } : {}) }), {
        headers: { Accept: 'application/json' }, credentials: 'same-origin',
      });
      if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new Error(body?.message || 'Export failed. Check your session and try again.');
      }
      if (!response.headers.get('content-type')?.includes('spreadsheetml')) {
        throw new Error('Your session may have expired. Sign in and try again.');
      }
      const blob = await response.blob();
      const signature = new Uint8Array(await blob.slice(0, 4).arrayBuffer());
      if (signature[0] !== 0x50 || signature[1] !== 0x4b || signature[2] !== 0x03 || signature[3] !== 0x04) {
        throw new Error('Export returned an invalid workbook. Please try again.');
      }
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `administrative-export-${from}-${to}.xlsx`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      setMessage('Workbook generated. Store downloaded files only in approved internal storage.');
    } catch (error) {
      setMessage(error.message || 'Export failed. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  if (role !== 'ADMIN') return null;

  const inputClass = 'w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus-visible:ring-2 focus-visible:ring-primary dark:border-slate-600 dark:bg-slate-900 dark:text-white';
  return (
    <details className="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
      <summary className="cursor-pointer font-semibold focus-visible:ring-2 focus-visible:ring-primary">Full Administrative Export</summary>
      <p id="administrative-export-help" className="mt-3 text-sm text-slate-600 dark:text-slate-300">
        Restricted management workbook, not a backup or anonymous data. Select case-created dates independently of report filters.
        Values reflect current state; linked referrals may fall outside the period. Client statistics count linked accounts once;
        unlinked cases are excluded from those statistics. Small groups are suppressed. Large exports must be narrowed by dates or agency.
      </p>
      <form onSubmit={download} aria-describedby="administrative-export-help" className="mt-3 flex flex-wrap items-end gap-3">
        <label className="flex min-w-0 max-w-full flex-col gap-1 text-sm">Export start date
          <input aria-label="Export start date" type="date" required value={from} onChange={(e) => setFrom(e.target.value)} disabled={busy} className={inputClass} />
        </label>
        <label className="flex min-w-0 max-w-full flex-col gap-1 text-sm">Export end date
          <input aria-label="Export end date" type="date" required min={from || undefined} value={to} onChange={(e) => setTo(e.target.value)} disabled={busy} className={inputClass} />
        </label>
        <label className="flex min-w-0 max-w-full flex-col gap-1 text-sm">Export agency
          <select aria-label="Export agency" value={agency} onChange={(e) => setAgency(e.target.value)} disabled={busy} className={inputClass}>
            <option value="">All agencies</option>
            {agencyOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
          </select>
        </label>
        <button type="submit" disabled={busy || !from || !to || from > to} className="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50">
          {busy ? 'Generating workbook…' : 'Download administrative XLSX'}
        </button>
      </form>
      <p role="status" aria-live="polite" className="mt-2 text-sm">{message}</p>
    </details>
  );
}
