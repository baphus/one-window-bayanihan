import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function CategoryForm({ show, onClose, category, allCategories }) {
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

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Slug</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Parent</th>
              <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">Articles</th>
              <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">Active</th>
              <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200">
            {categories?.map((cat) => (
              <tr key={cat.id} className="hover:bg-slate-50">
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2">
                    {cat.icon && (
                      <span className="material-symbols-outlined text-sm text-slate-400">{cat.icon}</span>
                    )}
                    <span className="text-sm font-semibold text-slate-800">{cat.name}</span>
                  </div>
                </td>
                <td className="px-6 py-4 text-xs text-slate-500">{cat.slug}</td>
                <td className="px-6 py-4 text-xs text-slate-500">{cat.parent?.name || '-'}</td>
                <td className="px-6 py-4 text-center text-xs text-slate-500">{cat.articles_count || 0}</td>
                <td className="px-6 py-4 text-center">
                  <span className={`inline-block h-2 w-2 rounded-full ${cat.is_active ? 'bg-green-500' : 'bg-slate-300'}`} />
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-2">
                    <button onClick={() => openEdit(cat)} className="text-xs font-semibold text-primary hover:underline">Edit</button>
                    <button onClick={() => deleteCategory(cat.id)} className="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {(!categories || categories.length === 0) && (
          <div className="py-12 text-center">
            <span className="material-symbols-outlined text-4xl text-slate-300 mb-3">category</span>
            <p className="text-sm text-slate-500">No categories yet. Create your first category.</p>
          </div>
        )}
      </div>

      <CategoryForm
        show={showForm}
        onClose={closeForm}
        category={editingCategory}
        allCategories={allCategories}
      />
    </AppLayout>
  );
}
