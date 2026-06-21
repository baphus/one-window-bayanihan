import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MarkdownEditor from '@/Components/Helpdesk/MarkdownEditor';
import { useState, useRef, useMemo } from 'react';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';
import InputError from '@/Components/InputError';
import { z } from 'zod';
import useClientValidation from '@/Hooks/useClientValidation';

const articleSchema = z.object({
  title: z.string().min(1, 'Title is required'),
  slug: z.string().min(1, 'Slug is required'),
  content_markdown: z.string().optional(),
  category_id: z.string().min(1, 'Category is required'),
  tag_ids: z.array(z.string()).optional(),
});

/* ---------- Inline Tag Creator ---------- */
function CreateTagModal({ open, onClose, onCreated }) {
  const [name, setName] = useState('');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');

  const handleCreate = async () => {
    const trimmed = name.trim();
    if (!trimmed) return;
    setCreating(true);
    setError('');
    try {
      const res = await fetch(route('admin.helpdesk.tags.store'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ name: trimmed, _quick: 1 }),
      });
      if (!res.ok) {
        const data = await res.json();
        setError(data?.errors?.name?.[0] || 'Failed to create tag');
        return;
      }
      const tag = await res.json();
      onCreated(tag);
      setName('');
      onClose();
    } catch {
      setError('Network error');
    } finally {
      setCreating(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h4 className="text-sm font-bold text-slate-900 mb-3">New Tag</h4>
        <input
          type="text"
          value={name}
          onChange={(e) => { setName(e.target.value); setError(''); }}
          onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
          placeholder="Tag name"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary mb-3"
          autoFocus
        />
        {error && <p className="text-xs text-red-500 mb-3">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
            Cancel
          </button>
          <button type="button" onClick={handleCreate} disabled={creating || !name.trim()} className="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
            {creating ? 'Creating...' : 'Create'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Inline Category Creator ---------- */
function CreateCategoryModal({ open, onClose, onCreated, existingCategories }) {
  const [name, setName] = useState('');
  const [parentId, setParentId] = useState('');
  const [icon, setIcon] = useState('');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');

  const handleCreate = async () => {
    const trimmed = name.trim();
    if (!trimmed) return;
    setCreating(true);
    setError('');
    try {
      const body = { name: trimmed, _quick: 1 };
      if (parentId) body.parent_id = parentId;
      if (icon.trim()) body.icon = icon.trim();

      const res = await fetch(route('admin.helpdesk.categories.store'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
      });
      if (!res.ok) {
        const data = await res.json();
        setError(data?.errors?.name?.[0] || 'Failed to create category');
        return;
      }
      const cat = await res.json();
      onCreated(cat);
      setName('');
      setParentId('');
      setIcon('');
      onClose();
    } catch {
      setError('Network error');
    } finally {
      setCreating(false);
    }
  };

  const parents = existingCategories?.filter((c) => !c.parent_id) || [];

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={onClose}>
      <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h4 className="text-sm font-bold text-slate-900 mb-3">New Category</h4>

        <label className="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Name</label>
        <input
          type="text"
          value={name}
          onChange={(e) => { setName(e.target.value); setError(''); }}
          onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
          placeholder="Category name"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary mb-3"
          autoFocus
        />

        <label className="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Parent (optional — leave blank for top-level)</label>
        <select
          value={parentId}
          onChange={(e) => setParentId(e.target.value)}
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary mb-3"
        >
          <option value="">— Top-level category —</option>
          {parents.map((p) => (
            <option key={p.id} value={p.id}>{p.name}</option>
          ))}
        </select>

        <label className="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Icon (optional — Material Symbol name)</label>
        <input
          type="text"
          value={icon}
          onChange={(e) => setIcon(e.target.value)}
          placeholder="e.g. folder, description, settings"
          className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary mb-3"
        />

        {error && <p className="text-xs text-red-500 mb-3">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">
            Cancel
          </button>
          <button type="button" onClick={handleCreate} disabled={creating || !name.trim()} className="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
            {creating ? 'Creating...' : 'Create'}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Main Component ---------- */
export default function Edit({ article, categories, tags }) {
  const isEditing = !!article;
  const { auth } = usePage().props;

  const [showTagModal, setShowTagModal] = useState(false);
  const [showCatModal, setShowCatModal] = useState(false);
  const [localCategories, setLocalCategories] = useState(categories || []);
  const [localTags, setLocalTags] = useState(tags || []);

  const { data, setData, post, patch, processing, errors, clearErrors, setError } = useForm({
    title: article?.title || '',
    slug: article?.slug || '',
    content_markdown: article?.content_markdown || '',
    excerpt: article?.excerpt || '',
    category_id: article?.category_id || '',
    status: article?.status || 'draft',
    featured: article?.featured || false,
    tag_ids: article?.tags?.map((t) => t.id) || [],
    edit_notes: '',
  });
  const { validate } = useClientValidation(articleSchema, data, setError);

  const articleInitialRef = useRef({
    title: article?.title || '',
    slug: article?.slug || '',
    content_markdown: article?.content_markdown || '',
    excerpt: article?.excerpt || '',
    category_id: article?.category_id || '',
    status: article?.status || 'draft',
    featured: article?.featured || false,
    tag_ids: article?.tags?.map((t) => t.id) || [],
  });
  const hasArticleDirty = useMemo(() => (
    data.title !== articleInitialRef.current.title
    || data.slug !== articleInitialRef.current.slug
    || data.content_markdown !== articleInitialRef.current.content_markdown
    || data.excerpt !== articleInitialRef.current.excerpt
    || data.category_id !== articleInitialRef.current.category_id
    || data.status !== articleInitialRef.current.status
    || data.featured !== articleInitialRef.current.featured
    || JSON.stringify(data.tag_ids) !== JSON.stringify(articleInitialRef.current.tag_ids)
  ), [data]);
  const { showModal, confirmNavigation, cancelNavigation, bypassNext } = useUnsavedChanges(hasArticleDirty || showTagModal || showCatModal);

  const titleToSlug = (title) => {
    return title
      .toLowerCase()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_]+/g, '-')
      .replace(/^-+|-+$/g, '');
  };

  const handleTitleChange = (value) => {
    setData('title', value);
    if (!isEditing) {
      setData('slug', titleToSlug(value));
    }
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    clearErrors();
    if (!validate()) return;
    bypassNext();
    if (isEditing) {
      patch(route('admin.helpdesk.articles.update', article.id), {
        preserveScroll: true,
      });
    } else {
      post(route('admin.helpdesk.articles.store'), {
        preserveScroll: true,
      });
    }
  };

  const toggleTag = (tagId) => {
    const current = data.tag_ids || [];
    if (current.includes(tagId)) {
      setData('tag_ids', current.filter((id) => id !== tagId));
    } else {
      setData('tag_ids', [...current, tagId]);
    }
  };

  const handleTagCreated = (tag) => {
    setLocalTags((prev) => [...prev, tag]);
    setData('tag_ids', [...(data.tag_ids || []), tag.id]);
  };

  const handleCategoryCreated = (cat) => {
    setLocalCategories((prev) => [...prev, cat]);
    setData('category_id', cat.id);
  };

  const parentCategories = localCategories?.filter((c) => !c.parent_id) || [];

  return (
    <AppLayout title={isEditing ? 'Edit Article' : 'New Article'}>
      <Head title={isEditing ? 'Edit Article' : 'New Article'} />
      <CreateTagModal open={showTagModal} onClose={() => setShowTagModal(false)} onCreated={handleTagCreated} />
      <CreateCategoryModal open={showCatModal} onClose={() => setShowCatModal(false)} onCreated={handleCategoryCreated} existingCategories={localCategories} />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">
            {isEditing ? 'Edit Article' : 'New Article'}
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            {isEditing ? 'Update the article content below.' : 'Create a new knowledge base article.'}
          </p>
        </div>
        <Link
          href={route('admin.helpdesk.articles.index')}
          className="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-100"
        >
          Back to Articles
        </Link>
      </div>

      <form onSubmit={handleSubmit}>
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="space-y-6 lg:col-span-2">
            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                    Title
                  </label>
                  <input
                    type="text"
                    value={data.title}
                    onChange={(e) => handleTitleChange(e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    placeholder="Enter article title"
                    required
                    maxLength={255}
                  />
                  <InputError message={errors.title} className="mt-1" />
                </div>

                <div>
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                    Slug
                  </label>
                  <input
                    type="text"
                    value={data.slug}
                    onChange={(e) => setData('slug', e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    placeholder="article-url-slug"
                    required
                    maxLength={255}
                  />
                  <InputError message={errors.slug} className="mt-1" />
                </div>

                <div>
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                    Excerpt
                  </label>
                  <textarea
                    value={data.excerpt}
                    onChange={(e) => setData('excerpt', e.target.value)}
                    rows={2}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    placeholder="Brief summary of the article"
                  />
                  <InputError message={errors.excerpt} className="mt-1" />
                </div>

                <div>
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-2">
                    Content (Markdown)
                  </label>
                  <MarkdownEditor
                    value={data.content_markdown}
                    onChange={(value) => setData('content_markdown', value || '')}
                    uploadUrl={route('admin.helpdesk.articles.upload-image')}
                  />
                  <InputError message={errors.content_markdown} className="mt-1" />
                </div>
              </div>
            </div>
          </div>

          <div className="space-y-6">
            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-4">
                Publishing
              </h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                    Status
                  </label>
                  <select
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </select>
                </div>

                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600">
                      Category
                    </label>
                    <button
                      type="button"
                      onClick={() => setShowCatModal(true)}
                      className="text-[10px] font-semibold text-primary hover:text-primary/80 transition-colors"
                    >
                      + New
                    </button>
                  </div>
                  <select
                    value={data.category_id}
                    onChange={(e) => setData('category_id', e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  >
                    <option value="">No category</option>
                    {parentCategories.map((cat) => {
                      const subcats = localCategories?.filter((c) => c.parent_id === cat.id) || [];
                      return (
                        <optgroup key={cat.id} label={cat.name}>
                          <option value={cat.id}>{cat.name}</option>
                          {subcats.map((sub) => (
                            <option key={sub.id} value={sub.id}>
                              &nbsp;&nbsp;&nbsp;{sub.name}
                            </option>
                          ))}
                        </optgroup>
                      );
                    })}
                  </select>
                  <InputError message={errors.category_id} className="mt-1" />
                </div>

                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="featured"
                    checked={data.featured}
                    onChange={(e) => setData('featured', e.target.checked)}
                    className="rounded border-slate-300 text-primary focus:ring-primary"
                  />
                  <label htmlFor="featured" className="text-xs font-semibold text-slate-700">
                    Featured article
                  </label>
                </div>
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                  Tags
                </h3>
                <button
                  type="button"
                  onClick={() => setShowTagModal(true)}
                  className="text-[10px] font-semibold text-primary hover:text-primary/80 transition-colors"
                >
                  + New
                </button>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {localTags?.map((tag) => (
                  <button
                    key={tag.id}
                    type="button"
                    onClick={() => toggleTag(tag.id)}
                    className={`rounded-full px-2.5 py-1 text-xs font-medium transition-colors ${
                      data.tag_ids?.includes(tag.id)
                        ? 'bg-primary text-white'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    {tag.name}
                  </button>
                ))}
                {(!localTags || localTags.length === 0) && (
                  <p className="text-xs text-slate-400">No tags yet.</p>
                )}
              </div>
              <InputError message={errors.tag_ids} className="mt-1" />
            </div>

            {isEditing && (
              <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-4">
                  Revision Notes
                </h3>
                <input
                  type="text"
                  value={data.edit_notes}
                  onChange={(e) => setData('edit_notes', e.target.value)}
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="What changed in this update?"
                />
                <InputError message={errors.edit_notes} className="mt-1" />
              </div>
            )}

            <div className="flex gap-2">
              <button
                type="submit"
                disabled={processing}
                className="flex-1 rounded-md bg-gray-800 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 disabled:opacity-50"
              >
                {processing ? 'Saving...' : isEditing ? 'Update Article' : 'Create Article'}
              </button>
            </div>
          </div>
        </div>
      </form>
      <UnsavedChangesModal show={showModal} onConfirm={confirmNavigation} onCancel={cancelNavigation} />
    </AppLayout>
  );
}
