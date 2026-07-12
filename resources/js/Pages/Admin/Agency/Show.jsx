import AppLayout from '@/Layouts/AppLayout';
import { Head, router, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StatusBadge from '@/Components/ui/StatusBadge';
import ServiceFormModal from '@/Components/Admin/ServiceFormModal';
import UserFormModal from '@/Components/Admin/UserFormModal';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';

import LogoUpload from '@/Components/LogoUpload';
import AgencyMapView from '@/Components/AgencyMapView';
import TableLoadingOverlay from '@/Components/ui/TableLoadingOverlay';
import useTableVisitLoading from '@/Hooks/useTableVisitLoading';

const TABS = ['Referrals', 'Services', 'Focal Persons'];

export default function AdminAgencyShow({ agency, referrals }) {
  const { auth } = usePage().props;
  const isAdmin = auth?.user?.role === 'ADMIN';

  const [activeTab, setActiveTab] = useState('Referrals');
  const [showForm, setShowForm] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [editingUser, setEditingUser] = useState(null);
  const [showUserForm, setShowUserForm] = useState(false);

  // Agency inline editor (ADMIN only)
  const [isEditing, setIsEditing] = useState(false);
  const [editData, setEditData] = useState(null);
  const [saving, setSaving] = useState(false);
  const [editErrors, setEditErrors] = useState({});
  const [logoFile, setLogoFile] = useState(null);
  const { isLoading: tableLoading, withLoading } = useTableVisitLoading();

  const { UnsavedModal, bypassNext } = useUnsavedChanges(showForm || showUserForm || isEditing);

  // ── Agency editor handlers (ADMIN only) ──

  function startEditing() {
    setEditData({
      name: agency.name,
      short: agency.short,
      description: agency.description || '',
      contact_info: agency.contact_info || '',
      logo_url: agency.logo_url || '',
      location_query: agency.location_query || '',
      latitude: agency.latitude ?? null,
      longitude: agency.longitude ?? null,
      map_link: agency.map_link || '',
      is_active: agency.is_active,
    });
    setLogoFile(null);
    setIsEditing(true);
    setEditErrors({});
  }

  function cancelEditing() {
    setIsEditing(false);
    setEditData(null);
    setLogoFile(null);
    setEditErrors({});
  }

  function handleSave(e) {
    e.preventDefault();
    bypassNext();
    setSaving(true);

    // Inertia auto-detects File objects in the data and converts to FormData
    const data = logoFile ? { ...editData, logo_url: logoFile } : { ...editData };

    router.patch(route('admin.agencies.update', agency.id), data, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditing(false);
        setEditData(null);
        setLogoFile(null);
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

  const inputClass = 'w-full border border-slate-200 rounded-md px-3 py-2 text-[13px] font-medium text-slate-700 outline-none focus:ring-1 focus:ring-blue-900';
  const errorClass = 'mt-1.5 text-xs text-red-600 font-medium';

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
  function openAddUser()       { setEditingUser(null); setShowUserForm(true); }
  function openEditUser(u)     { setEditingUser(u); setShowUserForm(true); }
  function closeForm()         { setShowForm(false); setShowUserForm(false); setEditingService(null); setEditingUser(null); }

  // ── Referral Pagination ──

  function handleReferralPage(page) {
    const url = new URL(window.location);
    url.searchParams.set('page', page);
    router.get(url.toString(), {}, withLoading({ preserveState: true, preserveScroll: true, only: ['referrals'] }));
  }

  // ── Helpers ──

  function formatDate(dateStr) {
    if (!dateStr) return '\u2014';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  // ── Render ──

  return (
    <AppLayout title={agency.name}>
      {showForm && editingService && (
        <ServiceFormModal service={editingService} allAgencies={[agency]} onClose={closeForm} onBypass={bypassNext} selectedAgencyId={!editingService?.id ? agency.id : undefined} />
      )}
      {showUserForm && (
        <UserFormModal user={editingUser} agencies={[agency]} onClose={closeForm} onBypass={bypassNext} selectedAgencyId={!editingUser?.id ? agency.id : undefined} />
      )}
      <Head title={agency.name} />

      {/* ── Header ── */}
      <header className="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">{agency.name}</h1>
          <p className="text-sm text-slate-400 font-body mt-0.5">Agency details, services, and focal persons.</p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          {isAdmin && !isEditing && (
            <button onClick={startEditing} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 shrink-0 transition-colors">
              Edit Agency
            </button>
          )}
          {isAdmin && (
            <Link href={route('admin.agencies.index')} className="px-4 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors shrink-0">&larr; Back to Agencies</Link>
          )}
        </div>
      </header>

      {/* ── Agency Editor / Info Cards ── */}
      <form onSubmit={handleSave}>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

          {/* Name */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Name</p>
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
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Short Name</p>
            {isEditing ? (
              <>
                <input type="text" value={editData.short} onChange={(e) => setField('short', e.target.value)} className={inputClass} />
                {editErrors.short && <p className={errorClass}>{editErrors.short}</p>}
              </>
            ) : (
              <p className="text-sm font-semibold text-slate-900">{agency.short}</p>
            )}
          </div>

          {/* Total Referrals */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Total Referrals</p>
            <p className="text-2xl font-black text-slate-900">{agency.referrals_count ?? 0}</p>
          </div>

          {/* Description */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 col-span-full">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Description</p>
            {isEditing ? (
              <>
                <textarea rows={3} value={editData.description} onChange={(e) => setField('description', e.target.value)} className={inputClass} />
                {editErrors.description && <p className={errorClass}>{editErrors.description}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900">{agency.description || '\u2014'}</p>
            )}
          </div>

          {/* Contact Info */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Contact Info</p>
            {isEditing ? (
              <>
                <textarea rows={2} value={editData.contact_info} onChange={(e) => setField('contact_info', e.target.value)} className={inputClass} />
                {editErrors.contact_info && <p className={errorClass}>{editErrors.contact_info}</p>}
              </>
            ) : (
              <p className="text-sm text-slate-900 whitespace-pre-wrap">{agency.contact_info || '\u2014'}</p>
            )}
          </div>

          {/* Status */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Status</p>
            {isEditing ? (
              <div className="flex items-center gap-2 mt-1">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={editData.is_active}
                  onChange={(e) => setField('is_active', e.target.checked)}
                  className="rounded border-slate-200 text-blue-900 focus:ring-blue-900 h-4 w-4"
                />
                <label htmlFor="is_active" className="text-[13px] text-slate-700 select-none font-medium">Active</label>
              </div>
            ) : (
              <StatusBadge status={agency.is_active ? 'ACTIVE' : 'INACTIVE'} />
            )}
          </div>

          {/* Logo */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Logo</p>
            {isEditing ? (
              <>
                <LogoUpload currentLogoUrl={editData.logo_url} onChange={setLogoFile} />
                {editErrors.logo_url && <p className={errorClass}>{editErrors.logo_url}</p>}
              </>
            ) : (
              agency.logo_url ? (
                <img src={agency.logo_url} alt={`${agency.name} logo`} className="max-h-16 rounded shadow" />
              ) : (
                <p className="text-sm text-slate-900">{'\u2014'}</p>
              )
            )}
          </div>

          {/* Location / Map */}
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 col-span-full">
            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Location</p>
            {isEditing ? (
              <>
                <label className="block text-xs font-medium text-slate-600 mb-1">Google Maps Location Link</label>
                <input
                  type="url"
                  value={editData.map_link}
                  onChange={(e) => setField('map_link', e.target.value)}
                  placeholder="Paste Google Maps share link..."
                  className={inputClass}
                  maxLength={2048}
                />
                <div className="mt-2">
                  <AgencyMapView
                    mapLink={editData.map_link}
                    latitude={editData.latitude}
                    longitude={editData.longitude}
                    locationQuery={editData.location_query}
                    agencyName={editData.name || 'Agency'}
                    embedHeight="180px"
                  />
                </div>
                {editErrors.latitude && <p className={errorClass}>{editErrors.latitude}</p>}
                {editErrors.longitude && <p className={errorClass}>{editErrors.longitude}</p>}
                {editErrors.map_link && <p className={errorClass}>{editErrors.map_link}</p>}
              </>
            ) : (
              <AgencyMapView
                mapLink={agency.map_link}
                latitude={agency.latitude}
                longitude={agency.longitude}
                locationQuery={agency.location_query}
                agencyName={agency.name}
                embedHeight="200px"
              />
            )}
          </div>
        </div>

        {/* ── Save / Cancel buttons (edit mode only) ── */}
        {isEditing && (
          <div className="flex items-center gap-3 mb-8">
            <button
              type="submit"
              disabled={saving}
              className="px-6 py-2 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 disabled:opacity-50 transition-colors"
            >
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
            <button
              type="button"
              onClick={cancelEditing}
              disabled={saving}
              className="px-6 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 disabled:opacity-50 transition-colors"
            >
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
                  ? 'border-blue-900 text-blue-900'
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
        <div className="relative" aria-busy={tableLoading}>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Referrals assigned to this agency.</p>
          </div>
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="px-4 py-3">Case No.</th>
                  <th className="px-4 py-3">Client</th>
                  <th className="px-4 py-3">Required Services</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Date Referred</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {referrals.data.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-slate-400">No referrals assigned to this agency.</td>
                  </tr>
                ) : (
                  referrals.data.map((ref) => (
                    <tr key={ref.id} className="hover:bg-slate-50">
                      <td className="px-4 py-3 font-medium text-slate-900">{ref.caseFile?.case_number || '\u2014'}</td>
                      <td className="px-4 py-3 text-slate-600">{ref.caseFile?.client ? `${ref.caseFile.client.first_name} ${ref.caseFile.client.last_name}` : '\u2014'}</td>
                      <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{ref.required_services || '\u2014'}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={ref.status} />
                      </td>
                      <td className="px-4 py-3 text-slate-600">{formatDate(ref.created_at)}</td>
                      <td className="px-4 py-3 text-right">
                        <Link href={route('referrals.show', ref.id)} className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300 inline-flex items-center">
                          View
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {/* ── Referral Pagination ── */}
          {referrals.data.length > 0 && referrals.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-xl shadow-sm mt-4">
              <div className="text-sm text-slate-500">
                Showing {referrals.from}–{referrals.to} of {referrals.total}
              </div>
              <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                <button
                  onClick={() => handleReferralPage(referrals.current_page - 1)}
                  disabled={referrals.current_page === 1}
                  className="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                >
                  <span className="sr-only">Previous</span>
                  <span className="material-symbols-outlined text-[20px]">chevron_left</span>
                </button>
                {Array.from({ length: referrals.last_page }, (_, i) => i + 1).map((p) => (
                  <button
                    key={p}
                    onClick={() => handleReferralPage(p)}
                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0 ${
                      p === referrals.current_page
                        ? 'z-10 bg-blue-900 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-900'
                        : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                    }`}
                  >
                    {p}
                  </button>
                ))}
                <button
                  onClick={() => handleReferralPage(referrals.current_page + 1)}
                  disabled={referrals.current_page === referrals.last_page}
                  className="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:opacity-50 focus:z-20 focus:outline-offset-0"
                >
                  <span className="sr-only">Next</span>
                  <span className="material-symbols-outlined text-[20px]">chevron_right</span>
                </button>
              </nav>
            </div>
          )}
          {tableLoading && <TableLoadingOverlay variant="table" />}
        </div>
      )}

      {/* ── Services Tab ── */}
      {activeTab === 'Services' && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-slate-500">Services offered by this agency.</p>
            {isAdmin && (
              <button onClick={openAddService} className="px-3 py-1.5 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors">
                + Add Service
              </button>
            )}
          </div>
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
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
                      <td className="px-4 py-3 text-slate-600 max-w-xs truncate">{service.description || '\u2014'}</td>
                      <td className="px-4 py-3 text-slate-600">{service.processing_days ?? '\u2014'}</td>
                      {isAdmin && (
                        <td className="px-4 py-3 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <button onClick={() => openEditService(service)} className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300">Edit</button>
                            <button onClick={() => handleDeleteService(service)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200">Delete</button>
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
              <button onClick={openAddUser} className="px-3 py-1.5 text-sm font-medium text-white bg-blue-900 rounded-md hover:bg-blue-800 transition-colors">
                + Add Focal Person
              </button>
            )}
          </div>
          <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
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
                      <td className="px-4 py-3 text-slate-600">{user.contact_number || '\u2014'}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={user.is_active ? 'ACTIVE' : 'INACTIVE'} />
                      </td>
                      {isAdmin && (
                        <td className="px-4 py-3 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <button onClick={() => openEditUser(user)} className="min-h-[28px] px-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[11px] font-bold rounded-md transition-colors border border-slate-300">Edit</button>
                            <button onClick={() => handleDeleteUser(user)} className="min-h-[28px] px-2.5 bg-red-50 text-red-600 hover:bg-red-100 text-[11px] font-bold rounded-md transition-colors border border-red-200">Deactivate</button>
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

      {UnsavedModal}
    </AppLayout>
  );
}
