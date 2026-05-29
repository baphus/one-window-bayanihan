import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

function CategoryForm({ show, onClose, category, allCategories, onBypass }) {
  const isEditing = !!category;

  const { data, setData, post, patch, processing, errors } = useForm({
    name: category?.name || '',
    slug: category?.slug || '',
    description: category?.description || '',
    parent_id: category?.parent_id || '',
    icon: category?.icon || '',
    sort_order: category?.sort_order ?? 0,
    is_active: category?.is_active ?? true,
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    onBypass?.();
    if (isEditing) {
      patch(route('admin.helpdesk.categories.update', category.id), {
        onSuccess: onClose,
      });
    } else {
      post(route('admin.helpdesk.categories.store'), {
        onSuccess: onClose,
      });
    }
  };

  if (!show) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-lg font-bold text-slate-900 mb-4">
          {isEditing ? 'Edit Category' : 'New Category'}
        </h2>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Name</label>
            <input
              type="text"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
          </div>

          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Slug</label>
            <input
              type="text"
              value={data.slug}
              onChange={(e) => setData('slug', e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>

          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Parent Category</label>
            <select
              value={data.parent_id}
              onChange={(e) => setData('parent_id', e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">None (top-level)</option>
              {allCategories?.filter((c) => !isEditing || c.id !== category.id).map((cat) => (
                <option key={cat.id} value={cat.id}>{cat.name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Icon (Material Symbol name)</label>
            <input
              type="text"
              value={data.icon}
              onChange={(e) => setData('icon', e.target.value)}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              placeholder="e.g. folder, badge, settings"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
              <input
                type="number"
                value={data.sort_order}
                onChange={(e) => setData('sort_order', parseInt(e.target.value))}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div className="flex items-end pb-2">
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={data.is_active}
                  onChange={(e) => setData('is_active', e.target.checked)}
                  className="rounded border-slate-300 text-primary"
                />
                <span className="text-xs font-semibold text-slate-700">Active</span>
              </label>
            </div>
          </div>

          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">Description</label>
            <textarea
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              rows={3}
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={processing}
              className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white disabled:opacity-50"
            >
              {processing ? 'Saving...' : isEditing ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function Categories({ categories, allCategories }) {
  const [showForm, setShowForm] = useState(false);
  const [editingCategory, setEditingCategory] = useState(null);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(showForm);

  const openCreate = () => {
    setEditingCategory(null);
    setShowForm(true);
  };

  const openEdit = (category) => {
    setEditingCategory(category);
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setEditingCategory(null);
  };

  const deleteCategory = (id) => {
    if (confirm('Delete this category? This action cannot be undone.')) {
      router.delete(route('admin.helpdesk.categories.destroy', id), {
        preserveScroll: true,
      });
    }
  };

  const columns = useMemo(() => [
    {
      key: 'name',
      title: 'Name',
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2">
          {row.icon && (
            <span className="material-symbols-outlined text-sm text-slate-400">{row.icon}</span>
          )}
          <span className="text-sm font-semibold text-slate-800">{row.name}</span>
        </div>
      ),
    },
    {
      key: 'slug',
      title: 'Slug',
      sortable: true,
      render: (row) => row.slug,
    },
    {
      key: 'parent',
      title: 'Parent',
      sortable: false,
      render: (row) => row.parent?.name || '-',
    },
    {
      key: 'articles_count',
      title: 'Articles',
      sortable: true,
      className: 'text-center',
      render: (row) => row.articles_count || 0,
    },
    {
      key: 'is_active',
      title: 'Active',
      sortable: true,
      className: 'text-center',
      render: (row) => (
        <span className={`inline-block h-2 w-2 rounded-full ${row.is_active ? 'bg-green-500' : 'bg-slate-300'}`} />
      ),
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      className: 'text-right',
      render: (row) => (
        <div className="flex items-center justify-end gap-2">
          <button onClick={() => openEdit(row)} className="text-xs font-semibold text-primary hover:underline">Edit</button>
          <button onClick={() => deleteCategory(row.id)} className="text-xs font-semibold text-red-600 hover:underline">Delete</button>
        </div>
      ),
    },
  ], []);

  return (
    <AppLayout title="Helpdesk Categories">
      <Head title="Helpdesk Categories" />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Categories</h1>
          <p className="text-sm text-slate-500 mt-1">Organize articles into categories.</p>
        </div>
        <button
          onClick={openCreate}
          className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
        >
          New Category
        </button>
      </div>

      <UnifiedTable
        columns={columns}
        data={categories ?? []}
        keyExtractor={(row) => row.id}
        hidePagination
      />

      <CategoryForm
        show={showForm}
        onClose={closeForm}
        category={editingCategory}
        allCategories={allCategories}
        onBypass={bypassNext}
      />
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
