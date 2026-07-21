import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useRef } from 'react';
import KpiCard from '@/Components/ui/KpiCard';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import { RowContextMenu, RowContextMenuItem } from '@/Components/ui/RowContextMenu';

export default function AgencyServicesIndex({ services, allServices }) {
    const { auth } = usePage().props;
    const [searchValue, setSearchValue] = useState('');
    const [viewMode, setViewMode] = useState('list');
    const [filterOpen, setFilterOpen] = useState(false);
    const [contextMenu, setContextMenu] = useState(null);

    const [selectedServiceId, setSelectedServiceId] = useState(null);
    const [draftName, setDraftName] = useState('');
    const [draftDescription, setDraftDescription] = useState('');
    const [draftRequirements, setDraftRequirements] = useState([]);
    const [newRequirement, setNewRequirement] = useState('');
    const [isEditOpen, setIsEditOpen] = useState(false);

    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [newName, setNewName] = useState('');
    const [newDescription, setNewDescription] = useState('');
    const [newRequirements, setNewRequirements] = useState([]);
    const [newReqInput, setNewReqInput] = useState('');

    const [deleteTarget, setDeleteTarget] = useState(null);
    const submitRef = useRef(null);
    const [creating, setCreating] = useState(false);
    const [updating, setUpdating] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const searchTimeout = useRef(null);

    function handleRowContextMenu(e, service) {
        e.preventDefault();
        setContextMenu({ x: e.clientX, y: e.clientY, row: service });
    }

    const selectedService = useMemo(
        () => allServices.find((s) => s.id === selectedServiceId) ?? null,
        [selectedServiceId, allServices],
    );

    const filteredServices = useMemo(() => {
        const query = searchValue.trim().toLowerCase();
        if (!query) return allServices;
        return allServices.filter((s) => {
            const searchable = [s.name, s.description, ...(s.requirements ?? []).map((r) => r.name)].join(' ').toLowerCase();
            return searchable.includes(query);
        });
    }, [allServices, searchValue]);

    const stats = useMemo(() => ({
        total: allServices.length,
        active: allServices.length,
        totalRequirements: allServices.reduce((sum, s) => sum + (s.requirements?.length ?? 0), 0),
    }), [allServices]);

    const openEdit = (service) => {
        setSelectedServiceId(service.id);
        setDraftName(service.name);
        setDraftDescription(service.description ?? '');
        setDraftRequirements((service.requirements ?? []).map((r) => r.name));
        setNewRequirement('');
        setIsEditOpen(true);
    };

    const closeEdit = () => {
        setIsEditOpen(false);
        setSelectedServiceId(null);
        setDraftName('');
        setDraftDescription('');
        setDraftRequirements([]);
        setNewRequirement('');
    };

    const addRequirement = (list, setter, input, setInput) => {
        const trimmed = input.trim();
        if (!trimmed) return;
        if (list.some((r) => r.toLowerCase() === trimmed.toLowerCase())) return;
        setter([...list, trimmed]);
        setInput('');
    };

    const removeRequirement = (list, setter, target) => {
        setter(list.filter((r) => r !== target));
    };

    const handleCreate = () => {
        if (!newName.trim() || !newDescription.trim()) return;
        setCreating(true);
        router.post(route('agency.services.store'), {
            name: newName.trim(),
            description: newDescription.trim(),
            requirements: newRequirements,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                setNewName('');
                setNewDescription('');
                setNewRequirements([]);
                setNewReqInput('');
            },
            onFinish: () => setCreating(false),
        });
    };

    const handleUpdate = () => {
        if (!draftName.trim() || !draftDescription.trim() || !selectedServiceId) return;
        setUpdating(true);
        router.patch(route('agency.services.update', selectedServiceId), {
            name: draftName.trim(),
            description: draftDescription.trim(),
            requirements: draftRequirements,
        }, {
            preserveScroll: true,
            onSuccess: closeEdit,
            onFinish: () => setUpdating(false),
        });
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('agency.services.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onFinish: () => setDeleting(false),
        });
    };

    const columns = [
        {
            key: 'name',
            title: 'SERVICE',
            render: (row) => <p className="font-bold text-slate-800">{row.name}</p>,
        },
        {
            key: 'description',
            title: 'DESCRIPTION',
            render: (row) => <p className="text-slate-600 text-sm">{row.description ?? '---'}</p>,
        },
        {
            key: 'requirements',
            title: 'REQUIREMENTS',
            render: (row) => {
                const reqs = row.requirements ?? [];
                return (
                    <div className="flex flex-wrap gap-1">
                        {reqs.slice(0, 3).map((r) => (
                            <span key={r.id} className="rounded bg-blue-50 px-2 py-0.5 text-[11px] font-bold text-blue-800">
                                {r.name}
                            </span>
                        ))}
                        {reqs.length > 3 && (
                            <span className="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">
                                +{reqs.length - 3} more
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            key: 'actions',
            title: 'ACTIONS',
            className: 'text-right',
            render: (row) => (
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => openEdit(row)}
                        className="h-8 rounded border border-slate-300 bg-white px-3 text-[11px] font-bold text-slate-700 hover:bg-slate-50"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={() => setDeleteTarget(row)}
                        className="h-8 rounded border border-red-200 bg-red-50 px-3 text-[11px] font-bold text-red-700 hover:bg-red-100"
                    >
                        Delete
                    </button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout title="Agency Services">
            <Head title="Agency Services" />

            <div data-tour="services-header" className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Agency Services</h1>
                <p className="text-sm text-slate-500 mt-1">
                    Manage the services offered by your agency to case managers and clients.
                </p>
            </div>

            <section data-tour="services-actions" className="grid grid-cols-1 gap-3 md:grid-cols-3 mb-6">
                <KpiCard title="Total Services" value={stats.total} icon="medical_services" iconBg="bg-blue-50" iconColor="text-blue-900" />
                <KpiCard title="Active Services" value={stats.active} icon="check_circle" iconBg="bg-emerald-50" iconColor="text-emerald-600" />
                <KpiCard title="Total Requirements" value={stats.totalRequirements} icon="checklist" iconBg="bg-violet-50" iconColor="text-violet-600" />
            </section>

            <div data-tour="services-list">
            <UnifiedTable
                data={filteredServices}
                columns={columns}
                keyExtractor={(row) => row.id}
                searchPlaceholder="Search services, descriptions, or requirements..."
                searchValue={searchValue}
                onSearchChange={setSearchValue}
                onAdvancedFilters={() => setFilterOpen(!filterOpen)}
                isAdvancedFiltersOpen={filterOpen}
                viewMode={viewMode}
                onViewModeChange={setViewMode}
                onNewRecord={() => setIsCreateOpen(true)}
                newRecordLabel="+ New Service"
                totalRecords={filteredServices.length}
                startIndex={filteredServices.length > 0 ? 1 : 0}
                endIndex={filteredServices.length}
                hidePagination
                onRowContextMenu={handleRowContextMenu}
            />
            </div>

            {isEditOpen && selectedService && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Edit Service</p>
                            <h2 className="text-xl font-bold text-slate-900">{selectedService.name}</h2>
                        </div>

                        <div className="space-y-5 px-6 py-5">
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Service Name</label>
                                <input type="text" value={draftName} onChange={(e) => setDraftName(e.target.value)}
                                    className="h-10 w-full rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Description</label>
                                <textarea value={draftDescription} onChange={(e) => setDraftDescription(e.target.value)} rows={3}
                                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Requirements</label>
                                <div className="space-y-2 mb-3">
                                    {draftRequirements.map((req) => (
                                        <div key={req} className="flex items-center justify-between rounded border border-slate-200 bg-slate-50 px-3 py-2">
                                            <span className="text-sm text-slate-700">{req}</span>
                                            <button type="button" onClick={() => removeRequirement(draftRequirements, setDraftRequirements, req)}
                                                className="text-xs font-bold text-red-600 hover:text-red-700">Remove</button>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    <input type="text" value={newRequirement} onChange={(e) => setNewRequirement(e.target.value)}
                                        placeholder="Enter a required document"
                                        className="h-10 flex-1 rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                    />
                                    <button type="button" onClick={() => addRequirement(draftRequirements, setDraftRequirements, newRequirement, setNewRequirement)}
                                        className="h-10 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800">Add</button>
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                            <button type="button" onClick={closeEdit}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" onClick={handleUpdate}
                                disabled={updating}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">{updating ? 'Saving…' : 'Save Changes'}</button>
                        </div>
                    </div>
                </div>
            )}

            {isCreateOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Create Service</p>
                            <h2 className="text-xl font-bold text-slate-900">Add New Service</h2>
                        </div>

                        <div className="space-y-5 px-6 py-5">
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Service Name</label>
                                <input type="text" value={newName} onChange={(e) => setNewName(e.target.value)}
                                    placeholder="Enter service name"
                                    className="h-10 w-full rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Description</label>
                                <textarea value={newDescription} onChange={(e) => setNewDescription(e.target.value)} rows={3}
                                    placeholder="Enter service description"
                                    className="w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Required Documents</label>
                                <div className="mb-3 flex gap-2">
                                    <input type="text" value={newReqInput} onChange={(e) => setNewReqInput(e.target.value)}
                                        placeholder="Enter a required document"
                                        className="h-10 flex-1 rounded border border-slate-300 px-3 text-sm text-slate-700 outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700"
                                    />
                                    <button type="button" onClick={() => addRequirement(newRequirements, setNewRequirements, newReqInput, setNewReqInput)}
                                        className="h-10 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800">Add</button>
                                </div>
                                <div className="space-y-2">
                                    {newRequirements.map((req) => (
                                        <div key={req} className="flex items-center justify-between rounded border border-slate-200 bg-slate-50 px-3 py-2">
                                            <span className="text-sm text-slate-700">{req}</span>
                                            <button type="button" onClick={() => removeRequirement(newRequirements, setNewRequirements, req)}
                                                className="text-xs font-bold text-red-600 hover:text-red-700">Remove</button>
                                        </div>
                                    ))}
                                    {newRequirements.length === 0 && (
                                        <p className="text-xs text-slate-500">Add at least one required document.</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                            <button type="button" onClick={() => { setIsCreateOpen(false); setNewName(''); setNewDescription(''); setNewRequirements([]); setNewReqInput(''); }}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" onClick={handleCreate}
                                disabled={creating}
                                className="h-9 rounded bg-blue-900 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed">{creating ? 'Creating…' : 'Create Service'}</button>
                        </div>
                    </div>
                </div>
            )}

            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <p className="mb-1 text-[11px] font-bold uppercase tracking-wider text-red-600">Delete Service</p>
                            <h2 className="text-lg font-bold text-slate-900">Confirm Deletion</h2>
                        </div>
                        <div className="px-6 py-5">
                            <p className="text-sm text-slate-600">
                                You are about to delete <span className="font-bold text-slate-900">{deleteTarget.name}</span>. This action cannot be undone.
                            </p>
                        </div>
                        <div className="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                            <button type="button" onClick={() => setDeleteTarget(null)}
                                className="h-9 rounded border border-slate-300 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" onClick={handleDelete}
                                disabled={deleting}
                                className="h-9 rounded bg-red-600 px-4 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">{deleting ? 'Deleting…' : 'Delete Service'}</button>
                        </div>
                    </div>
                </div>
            )}
            {contextMenu && (
                <RowContextMenu x={contextMenu.x} y={contextMenu.y} onClose={() => setContextMenu(null)}>
                    <RowContextMenuItem icon="edit" label="Edit" onClick={() => {
                        openEdit(contextMenu.row);
                        setContextMenu(null);
                    }} />
                    <RowContextMenuItem icon="delete" label="Delete" variant="danger" onClick={() => {
                        setDeleteTarget(contextMenu.row);
                        setContextMenu(null);
                    }} />
                </RowContextMenu>
            )}
        </AppLayout>
    );
}
