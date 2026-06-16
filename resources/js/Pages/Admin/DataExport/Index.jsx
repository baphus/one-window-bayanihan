import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { FileDown } from 'lucide-react';

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
    return (
        <AppLayout title="Data Export">
            <Head title="Data Export" />

            <div className="mb-8 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Data Export</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Export all business data as a formatted Excel workbook with 14 data sheets.
                    </p>
                </div>
                <button
                    onClick={() => window.open(route('admin.data-export.export'))}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] transition-colors"
                >
                    <FileDown className="w-4 h-4" />
                    Export All Data as Excel
                </button>
            </div>

            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100">
                    <h2 className="text-sm font-semibold text-slate-700">
                        Included Sheets ({tables.length})
                    </h2>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Each table below becomes one sheet in the exported workbook.
                    </p>
                </div>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 border-b border-slate-100">
                            <th className="text-left px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider w-12">#</th>
                            <th className="text-left px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Sheet Name</th>
                            <th className="text-left px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Table</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
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
