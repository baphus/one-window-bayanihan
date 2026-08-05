import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const SHEET_LABELS = {
    cases:              'Cases',
    clients:            'Clients',
    referrals:          'Referrals',
    users:              'Users',
    agencies:           'Agencies',
    services:           'Services',
    milestones:         'Milestones',
    next_of_kin:        'Next of Kin',
    feedback:           'Feedback',
    case_documents:     'Case Documents',
    client_addresses:   'Client Addresses',
    client_employments: 'Client Employments',
    case_categories:    'Case Categories',
    case_statuses:      'Case Statuses',
};

export default function DataExportIndex({ tables }) {
    // Guard against repeat presses: exporting the full workbook can be slow and
    // `window.open` would happily spawn a new tab per click. Re-enable after a
    // short cooldown so a legitimate second export is still possible.
    const [exporting, setExporting] = useState(false);
    const timeoutRef = useRef(null);

    useEffect(() => {
        return () => {
            if (timeoutRef.current) window.clearTimeout(timeoutRef.current);
        };
    }, []);

    const handleExport = () => {
        if (exporting) return;
        setExporting(true);
        window.open(route('admin.data-export.export'));
        timeoutRef.current = window.setTimeout(() => setExporting(false), 3000);
    };

    return (
        <AppLayout title="Data Export">
            <Head title="Data Export" />

            <div data-tour="data-export-header" className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">Data Export</h1>
                    <p className="text-sm text-slate-400 font-body mt-0.5">
                        Export all business data as a formatted Excel workbook with 14 data sheets.
                    </p>
                </div>
                <button
                    data-tour="data-export-button"
                    onClick={handleExport}
                    disabled={exporting}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <FileDown className="w-4 h-4" />
                    Export All Data as Excel
                </button>
            </div>

            <div data-tour="data-export-sheets" className="bg-white rounded-lg border border-slate-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100">
                    <h2 className="text-sm font-semibold text-slate-700">
                        Included Sheets ({tables.length})
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Each table below becomes one sheet in the exported workbook.
                    </p>
                </div>
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="bg-slate-50 border-b border-slate-300">
                            <th className="px-6 py-4 text-left text-[12px] font-extrabold uppercase tracking-widest text-slate-500 w-12">#</th>
                            <th className="px-6 py-4 text-left text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Sheet Name</th>
                            <th className="px-6 py-4 text-left text-[12px] font-extrabold uppercase tracking-widest text-slate-500">Table</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-200 bg-white">
                        {tables.map((table, index) => (
                            <tr key={table} className="hover:bg-slate-50/50 transition-colors">
                                <td className="px-6 py-3 text-slate-400 text-xs">{index + 1}</td>
                                <td className="px-6 py-3 font-medium text-slate-800">
                                    {SHEET_LABELS[table] ?? table}
                                </td>
                                <td className="px-6 py-3 text-slate-500 font-mono text-xs">{table}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
