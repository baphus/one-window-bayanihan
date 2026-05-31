import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';
import ServiceFormModal from '@/Components/Admin/ServiceFormModal';
import UserFormModal from '@/Components/Admin/UserFormModal';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

const TABS = ['Services', 'Focal Persons'];

export default function AdminAgencyShow({ agency }) {
  const [activeTab, setActiveTab] = useState('Services');
  const [showForm, setShowForm] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [editingUser, setEditingUser] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

  function handleDeleteService(service) {
    if (!confirm(`Delete service "${service.name}"? This action cannot be undone.`)) return;
    router.delete(route('admin.services.destroy', service.id), { preserveScroll: true });
  }

  function handleDeleteUser(user) {
    if (!confirm(`Deactivate user "${user.name}"?`)) return;
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
  }

  function openAddService() {
    setEditingService({ agcy_id: agency.id });
    setShowForm(true);
  }

  function openEditService(service) {
    setEditingService(service);
    setShowForm(true);
  }

  function openAddUser() {
    setEditingUser({ role: 'AGENCY', agcy_id: agency.id });
    setShowForm(true);
  }

  function openEditUser(user) {
    setEditingUser(user);
    setShowForm(true);
  }

  function closeForm() {
    setShowForm(false);
    setEditingService(null);
    setEditingUser(null);
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
      {showForm && editingService && (
        <ServiceFormModal service={editingService} allAgencies={[agency]} onClose={closeForm} onBypass={bypassNext} />
      )}
      {showForm && editingUser && (
        <UserFormModal user={editingUser} agencies={[agency]} onClose={closeForm} onBypass={bypassNext} />
      )}
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
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Services offered by this agency.</p>
            <button onClick={openAddService} className="px-3 py-1.5 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
              + Add Service
            </button>
          </div>
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
                        <div className="flex items-center justify-end gap-1.5">
                          <button onClick={() => openEditService(service)} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
                          <button onClick={() => handleDeleteService(service)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Focal Persons Tab */}
      {activeTab === 'Focal Persons' && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Agency focal persons for this agency.</p>
            <button onClick={openAddUser} className="px-3 py-1.5 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
              + Add Focal Person
            </button>
          </div>
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Email</th>
                  <th className="px-4 py-3">Contact Number</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {agency.users.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-slate-400">No focal persons assigned to this agency.</td>
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
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button onClick={() => openEditUser(user)} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
                          <button onClick={() => handleDeleteUser(user)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Deactivate</button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
