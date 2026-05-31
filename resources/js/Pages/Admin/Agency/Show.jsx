import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';
import ServiceFormModal from '@/Components/Admin/ServiceFormModal';
import UserFormModal from '@/Components/Admin/UserFormModal';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

const TABS = ['Referrals', 'Services', 'Focal Persons'];

export default function AdminAgencyShow({ agency }) {
  const { auth } = usePage().props;
  const isAdmin = auth?.user?.role === 'ADMIN';

  const [activeTab, setActiveTab] = useState('Referrals');
  const [showForm, setShowForm] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [editingUser, setEditingUser] = useState(null);

  // Agency inline editor (ADMIN only)
  const [isEditing, setIsEditing] = useState(false);
  const [editData, setEditData] = useState(null);
  const [saving, setSaving] = useState(false);
  const [editErrors, setEditErrors] = useState({});

  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm || isEditing);

  // ── Agency editor handlers (ADMIN only) ──

  function startEditing() {
    setEditData({
      name: agency.name,
      short: agency.short,
      description: agency.description || '',
      contact_info: agency.contact_info || '',
      logo_url: agency.logo_url || '',
      location_query: agency.location_query || '',
      is_active: agency.is_active,
    });
    setIsEditing(true);
    setEditErrors({});
  }

  function cancelEditing() {
    setIsEditing(false);
    setEditData(null);
    setEditErrors({});
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    router.patch(route('admin.agencies.update', agency.id), editData, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditing(false);
        setEditData(null);
        setEditErrors({});
      },
      onError: (errors) => {
        setEditErrors(errors);
      },
      onFinish: () => setSaving(false),
    });
  }

  function setField(field, value) {
    setEditData((prev) => ({ ...prev, [field]: value }));
  }

  const inputClass = 'mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm';
  const errorClass = 'mt-1 text-sm text-red-600';

  // ── Service / User modal handlers ──

  function handleDeleteService(service) {
    if (!confirm(`Delete service "${service.name}"? This action cannot be undone.`)) return;
    router.delete(route('admin.services.destroy', service.id), { preserveScroll: true });
  }

  function handleDeleteUser(user) {
    if (!confirm(`Deactivate user "${user.name}"?`)) return;
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
  }

  function openAddService()    { setEditingService({ agcy_id: agency.id }); setShowForm(true); }
  function openEditService(s)  { setEditingService(s); setShowForm(true); }
  function openAddUser()       { setEditingUser({ role: 'AGENCY', agcy_id: agency.id }); setShowForm(true); }
  function openEditUser(u)     { setEditingUser(u); setShowForm(true); }
  function closeForm()         { setShowForm(false); setEditingService(null); setEditingUser(null); }

  // ── Helpers ──

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ── Render ──

  return (
    <AppLayout title={agency.name}>
      {showForm && editingService && (
        <ServiceFormModal service={editingService} allAgencies={[agency]} onClose={closeForm} onBypass={bypassNext} />
      )}
      {showForm && editingUser && (
        <UserFormModal user={editingUser} agencies={[agency]} onClose={closeForm} onBypass={bypassNext} />
      )}
      <Head title={agency.name} />

      {/* ── Header ── */}
      <div className="mb-8 flex items-start justify-between">
        <div>
          {isAdmin && (
            <Link href={route('admin.agencies.index')} className="text-sm text-[#0b5384] hover:underline mb-2 inline-block">&larr; Back to Agencies</Link>
          )}
          <h1 className="text-2xl font-bold text-slate-900 mt-1">{agency.name}</h1>
        </div>
        {isAdmin && !isEditing && (
          <button onClick={startEditing} className="px-4 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] shrink-0">
            Edit Agency
          </button>
        )}
      </div>

      {/* ── Agency Editor / Info Cards ── */}
      <form onSubmit={handleSave}>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

          {/* Name */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Name</p>
            {isEditing ? (
              <>
                <input type="text" value={editData.name} onChange={(e) => setField('name', e.target.value)} className={inputClass} />
                {editErrors.name && <p className={errorClass}>{editErrors.name}</p>}
              </>
            ) : (
              <p className="text-sm font-semibold text-slate-900">{agency.name}</p>
            )}
          </div>

          {/* Short Name */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Short Name</p>
            {isEditing ? (
              <>
                <input type="text" value={editData.short} onChange={(e) => setField('short', e.target.value)} className={inputClass} />
                {editErrors.short && <p className={errorClass}>{editErrors.short}</p>}
              </>
            ) : (
              <p className="text-sm font-semibold text-slate-900">{agency.short}</p>
            )}
          </div>

          {/* Slug (read-only) */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Slug</p>
            <p className="text-sm font-semibold text-slate-900">{agency.slug}</p>
          </div>

          {/* Description */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4 md:col-span-2">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Description</p>
            {isEditing ? (
              <>
                <textarea rows={3} value={editData.description} onChange={(e) => setField('description', e.target.value)} className={inputClass} />
                {editErrors.description && <p className={errorClass}>{editErrors.description}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900">{agency.description || '—'}</p>
            )}
          </div>

          {/* Contact Info */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Contact Info</p>
            {isEditing ? (
              <>
                <textarea rows={2} value={editData.contact_info} onChange={(e) => setField('contact_info', e.target.value)} className={inputClass} />
                {editErrors.contact_info && <p className={errorClass}>{editErrors.contact_info}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900 whitespace-pre-wrap">{agency.contact_info || '—'}</p>
            )}
          </div>

          {/* Logo URL */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Logo URL</p>
            {isEditing ? (
              <>
                <input type="text" value={editData.logo_url} onChange={(e) => setField('logo_url', e.target.value)} className={inputClass} />
                {editErrors.logo_url && <p className={errorClass}>{editErrors.logo_url}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900 truncate">{agency.logo_url || '—'}</p>
            )}
          </div>

          {/* Location Query */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Location Query</p>
            {isEditing ? (
              <>
                <input type="text" value={editData.location_query} onChange={(e) => setField('location_query', e.target.value)} className={inputClass} />
                {editErrors.location_query && <p className={errorClass}>{editErrors.location_query}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900">{agency.location_query || '—'}</p>
            )}
          </div>

          {/* Status */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Status</p>
            {isEditing ? (
              <div className="flex items-center gap-2 mt-2">
                <input type="checkbox" id="is_active" checked={editData.is_active} onChange={(e) => setField('is_active', e.target.checked)} className="rounded border-slate-300 h-4 w-4" />
                <label htmlFor="is_active" className="text-sm text-slate-700 select-none">Active</label>
              </div>
            ) : (
              <StatusBadge status={agency.is_active ? 'ACTIVE' : 'INACTIVE'} />
            )}
          </div>

          {/* Referrals (read-only) */}
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 p-4">
            <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Referrals</p>
            <p className="text-sm font-semibold text-slate-900">{agency.referrals_count ?? 0}</p>
          </div>
        </div>

        {/* ── Save / Cancel buttons (edit mode only) ── */}
        {isEditing && (
          <div className="flex items-center gap-3 mb-8 pb-6 border-b border-slate-200">
            <button type="submit" disabled={saving} className="px-6 py-2 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a] disabled:opacity-50">
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
            <button type="button" onClick={cancelEditing} disabled={saving} className="px-6 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 disabled:opacity-50">
              Cancel
            </button>
          </div>
        )}
      </form>

      {/* ── Tabs ── */}
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

      {/* ── Referrals Tab ── */}
      {activeTab === 'Referrals' && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Referrals assigned to this agency.</p>
          </div>
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="px-4 py-3">Case No.</th>
                  <th className="px-4 py-3">Client</th>
                  <th className="px-4 py-3">Required Services</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Date Referred</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {agency.referrals.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-slate-400">No referrals assigned to this agency.</td>
                  </tr>
                ) : (
                  agency.referrals.map((ref) => (
                    <tr key={ref.id} className="hover:bg-slate-50">
                      <td className="px-4 py-3 font-medium text-slate-900">
                        <Link href={route('referrals.show', ref.id)} className="text-[#0b5384] hover:underline">
                          {ref.caseFile?.case_number || '—'}
                        </Link>
                      </td>
                      <td className="px-4 py-3 text-slate-600">{ref.caseFile?.client?.name || '—'}</td>
                      <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{ref.required_services || '—'}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={ref.status} />
                      </td>
                      <td className="px-4 py-3 text-slate-600">{formatDate(ref.created_at)}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Services Tab ── */}
      {activeTab === 'Services' && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Services offered by this agency.</p>
            {isAdmin && (
              <button onClick={openAddService} className="px-3 py-1.5 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
                + Add Service
              </button>
            )}
          </div>
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Description</th>
                  <th className="px-4 py-3">Processing Days</th>
                  {isAdmin && <th className="px-4 py-3 text-right">Actions</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {agency.services.length === 0 ? (
                  <tr>
                    <td colSpan={isAdmin ? 4 : 3} className="px-4 py-8 text-center text-slate-400">No services assigned to this agency.</td>
                  </tr>
                ) : (
                  agency.services.map((service) => (
                    <tr key={service.id} className="hover:bg-slate-50">
                      <td className="px-4 py-3 font-medium text-slate-900">{service.name}</td>
                      <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{service.description || '—'}</td>
                      <td className="px-4 py-3 text-slate-600">{service.processing_days ?? '—'}</td>
                      {isAdmin && (
                        <td className="px-4 py-3 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <button onClick={() => openEditService(service)} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
                            <button onClick={() => handleDeleteService(service)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Delete</button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Focal Persons Tab ── */}
      {activeTab === 'Focal Persons' && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Agency focal persons for this agency.</p>
            {isAdmin && (
              <button onClick={openAddUser} className="px-3 py-1.5 text-sm font-medium text-white bg-[#0b5384] rounded-md hover:bg-[#09416a]">
                + Add Focal Person
              </button>
            )}
          </div>
          <div className="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Email</th>
                  <th className="px-4 py-3">Contact Number</th>
                  <th className="px-4 py-3">Status</th>
                  {isAdmin && <th className="px-4 py-3 text-right">Actions</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {agency.users.length === 0 ? (
                  <tr>
                    <td colSpan={isAdmin ? 5 : 4} className="px-4 py-8 text-center text-slate-400">No focal persons assigned to this agency.</td>
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
                      {isAdmin && (
                        <td className="px-4 py-3 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <button onClick={() => openEditUser(user)} className="min-h-[28px] px-2.5 bg-[#f1f5f9] text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-[3px] transition-colors border border-slate-300">Edit</button>
                            <button onClick={() => handleDeleteUser(user)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-[3px] transition-colors border border-red-200">Deactivate</button>
                          </div>
                        </td>
                      )}
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
