import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

function TagForm({ show, onClose, tag }) {
  const isEditing = !!tag;

  const { data, setData, post, patch, processing, errors } = useForm({
    name: tag?.name || '',
    slug: tag?.slug || '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (isEditing) {
      patch(route('admin.helpdesk.tags.update', tag.id), {
        onSuccess: onClose,
      });
    } else {
      post(route('admin.helpdesk.tags.store'), {
        onSuccess: onClose,
      });
    }
  };

  if (!show) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-lg font-bold text-slate-900 mb-4">
          {isEditing ? 'Edit Tag' : 'New Tag'}
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

export default function Tags({ tags }) {
  const [showForm, setShowForm] = useState(false);
  const [editingTag, setEditingTag] = useState(null);

  const openCreate = () => {
    setEditingTag(null);
    setShowForm(true);
  };

  const openEdit = (tag) => {
    setEditingTag(tag);
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setEditingTag(null);
  };

  const deleteTag = (id) => {
    if (confirm('Delete this tag?')) {
      router.delete(route('admin.helpdesk.tags.destroy', id), {
        preserveScroll: true,
      });
    }
  };

  return (
    <AppLayout title="Helpdesk Tags">
      <Head title="Helpdesk Tags" />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Tags</h1>
          <p className="text-sm text-slate-500 mt-1">Manage article tags for filtering and organization.</p>
        </div>
        <button
          onClick={openCreate}
          className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
        >
          New Tag
        </button>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Slug</th>
              <th className="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500">Articles</th>
              <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200">
            {tags?.map((tag) => (
              <tr key={tag.id} className="hover:bg-slate-50">
                <td className="px-6 py-4">
                  <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                    {tag.name}
                  </span>
                </td>
                <td className="px-6 py-4 text-xs text-slate-500">{tag.slug}</td>
                <td className="px-6 py-4 text-center text-xs text-slate-500">{tag.articles_count || 0}</td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-2">
                    <button onClick={() => openEdit(tag)} className="text-xs font-semibold text-primary hover:underline">Edit</button>
                    <button onClick={() => deleteTag(tag.id)} className="text-xs font-semibold text-red-600 hover:underline">Delete</button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {(!tags || tags.length === 0) && (
          <div className="py-12 text-center">
            <span className="material-symbols-outlined text-4xl text-slate-300 mb-3">label</span>
            <p className="text-sm text-slate-500">No tags yet. Create your first tag.</p>
          </div>
        )}
      </div>

      <TagForm
        show={showForm}
        onClose={closeForm}
        tag={editingTag}
      />
    </AppLayout>
  );
}
