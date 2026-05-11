import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

export default function ClientIndex({ clients }) {
  return (
    <AppLayout title="Clients">
      <Head title="Clients" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Clients</h1>
        <p className="text-sm text-slate-500 mt-1">View all registered clients and their associated cases.</p>
      </div>

      <div className="rounded-lg bg-white shadow-sm border border-slate-200">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Name</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Sex</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Date of Birth</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Referrals</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {clients.data.length === 0 ? (
                <tr><td colSpan={5} className="px-6 py-4 text-center text-sm text-slate-500">No clients found.</td></tr>
              ) : (
                clients.data.map((client) => (
                  <tr key={client.id} className="hover:bg-slate-50">
                    <td className="px-6 py-4 text-sm font-medium text-slate-900">
                      {[client.first_name, client.middle_name, client.last_name, client.suffix].filter(Boolean).join(' ')}
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-500">{client.sex || 'N/A'}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">
                      {client.date_of_birth ? new Date(client.date_of_birth).toLocaleDateString() : 'N/A'}
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-500">
                      {client.case_file ? (
                        <Link href={route('cases.show', client.case_file.id)} className="text-indigo-600 hover:text-indigo-900">
                          {client.case_file.case_number}
                        </Link>
                      ) : 'N/A'}
                    </td>
                    <td className="px-6 py-4 text-sm text-slate-500">
                      {client.case_file?.referrals?.length ?? 0}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {clients.last_page > 1 && (
          <div className="border-t border-slate-200 px-6 py-4 flex items-center justify-between">
            <div className="text-sm text-slate-700">
              Showing {clients.from} to {clients.to} of {clients.total}
            </div>
            <div className="flex gap-2">
              {clients.links.map((link, i) => (
                <Link
                  key={i}
                  href={link.url || '#'}
                  className={`inline-flex items-center rounded-md px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
