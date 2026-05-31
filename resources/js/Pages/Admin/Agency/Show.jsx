import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';

const TABS = ['Services', 'Focal Persons'];

export default function AdminAgencyShow({ agency }) {
  const [activeTab, setActiveTab] = useState('Services');

  function handleDeleteService(service) {
    if (!confirm(`Delete service "${service.name}"? This action cannot be undone.`)) return;
    router.delete(route('admin.services.destroy', service.id), { preserveScroll: true });
  }

  const infoCards = useMemo(() => [
    { label: 'Short Name', value: agency.short },
    { label: 'Description', value: agency.description || '—' },
    { label: 'Contact Info', value: agency.contact_info || '—' },
    { label: 'Location Query', value: agency.location_query || '—' },
    { label: 'Total Referrals', value: agency.referrals_count ?? 0 },
    { label: 'Status', value: <StatusBadge status={agency.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ], [agency]);

  return (
    <AppLayout title={agency.name}>
      <Head title={agency.name} />
      <div className="mb-8">
        <Link href={route('admin.agencies.index')} className="text-sm text-[#0b5384] hover:underline mb-2 inline-block">&larr; Back to Agencies</Link>
        <h1 className="text-2xl font-bold text-slate-900 mt-1">{agency.name}</h1>
      </div>

      {/* Info Card Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        {infoCards.map((card) => (
          <div key={card.label} className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">{card.label}</p>
            <p className="text-sm font-semibold text-slate-900">{card.value}</p>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div className="border-b border-slate-200 mb-6">
        <nav className="flex gap-6">
          {TABS.map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
                activeTab === tab
                  ? 'border-[#0b5384] text-[#0b5384]'
                  : 'border-transparent text-slate-500 hover:text-slate-700'
              }`}
            >
              {tab}
            </button>
          ))}
        </nav>
      </div>

      {/* Services Tab */}
      {activeTab === 'Services' && (
        <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Description</th>
                <th className="px-4 py-3">Processing Days</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {agency.services.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-slate-400">No services assigned to this agency.</td>
                </tr>
              ) : (
                agency.services.map((service) => (
                  <tr key={service.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{service.name}</td>
                    <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{service.description || '—'}</td>
                    <td className="px-4 py-3 text-slate-600">{service.processing_days ?? '—'}</td>
                    <td className="px-4 py-3 text-right">
                      <button
                        onClick={() => handleDeleteService(service)}
                        className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Focal Persons Tab */}
      {activeTab === 'Focal Persons' && (
        <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Email</th>
                <th className="px-4 py-3">Contact Number</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {agency.users.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-slate-400">No focal persons assigned to this agency.</td>
                </tr>
              ) : (
                agency.users.map((user) => (
                  <tr key={user.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-medium text-slate-900">{user.name}</td>
                    <td className="px-4 py-3 text-slate-600">{user.email}</td>
                    <td className="px-4 py-3 text-slate-600">{user.contact_number || '—'}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </AppLayout>
  );
}
