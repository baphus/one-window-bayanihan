import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { Chart } from 'chart.js';
import { ReportsDashboard } from './Index';

export default function PrintReport(props) {
  useEffect(() => {
    const before = () => Object.values(Chart.instances).forEach((chart) => chart.resize(600, 260));
    const after = () => Object.values(Chart.instances).forEach((chart) => chart.resize());
    window.addEventListener('beforeprint', before);
    window.addEventListener('afterprint', after);
    return () => {
      window.removeEventListener('beforeprint', before);
      window.removeEventListener('afterprint', after);
    };
  }, []);
  return <main className="mx-auto max-w-7xl bg-slate-50 p-4 text-slate-900 print:bg-white print:p-0">
    <Head title="Print reports" />
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
      <p className="text-sm">Preview both report sections. Choose “Save as PDF” in your browser’s print dialog.</p>
      <button type="button" onClick={() => window.print()} className="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-primary">Print / Save PDF</button>
    </div>
    <p className="mb-3 text-sm">Reporting period: {props.from} – {props.to}. {props.province && `Province: ${props.province}. `}{props.city && `City: ${props.city}. `}Prepared: {props.metadata?.generated_at || 'current session'}.</p>
    {props.agencyId && <p className="mb-3 text-sm">Agency scope: {props.agencyId}</p>}
    {props.suppression?.applied && <p className="mb-4 rounded-md border border-slate-300 p-3 text-sm">Privacy protection applied: small groups and complementary values withheld. Missing values are not zero.</p>}
    <ReportsDashboard {...props} printMode />
    <style>{`@page { size: A4 landscape; margin: 12mm; } @media print { html, body { background: white !important; color: #0f172a; } * { print-color-adjust: exact; -webkit-print-color-adjust: exact; } canvas { max-width: 100% !important; } h2, h3 { break-after: avoid; } section { box-shadow: none !important; } .overflow-y-auto { overflow: visible !important; max-height: none !important; } }`}</style>
  </main>;
}
