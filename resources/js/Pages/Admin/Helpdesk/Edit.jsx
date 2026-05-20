import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import MarkdownEditor from '@/Components/Helpdesk/MarkdownEditor';
import { useEffect } from 'react';

export default function Edit({ article, categories, tags }) {
  const isEditing = !!article;
  const { auth } = usePage().props;

  const { data, setData, post, patch, processing, errors } = useForm({
    title: article?.title || '',
    slug: article?.slug || '',
    content_markdown: article?.content_markdown || '',
    excerpt: article?.excerpt || '',
    category_id: article?.category_id || '',
    status: article?.status || 'draft',
    featured: article?.featured || false,
    visibility: article?.visibility || 'public',
    target_roles: article?.target_roles || [],
    tag_ids: article?.tags?.map((t) => t.id) || [],
    edit_notes: '',
  });

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

  const toggleRole = (role) => {
    const current = data.target_roles || [];
    if (current.includes(role)) {
      setData('target_roles', current.filter((r) => r !== role));
    } else {
      setData('target_roles', [...current, role]);
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

  return (
    <AppLayout title={isEditing ? 'Edit Article' : 'New Article'}>
      <Head title={isEditing ? 'Edit Article' : 'New Article'} />

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
                  />
                  {errors.title && (
                    <p className="mt-1 text-xs text-red-500">{errors.title}</p>
                  )}
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
                  />
                  {errors.slug && (
                    <p className="mt-1 text-xs text-red-500">{errors.slug}</p>
                  )}
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
                  {errors.excerpt && (
                    <p className="mt-1 text-xs text-red-500">{errors.excerpt}</p>
                  )}
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
                  {errors.content_markdown && (
                    <p className="mt-1 text-xs text-red-500">{errors.content_markdown}</p>
                  )}
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
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                    Category
                  </label>
                  <select
                    value={data.category_id}
                    onChange={(e) => setData('category_id', e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  >
                    <option value="">No category</option>
                    {categories?.map((cat) => (
                      <option key={cat.id} value={cat.id}>
                        {cat.name}
                      </option>
                    ))}
                  </select>
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
              <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-4">
                Visibility
              </h3>
              <div className="space-y-3">
                <div>
                  <select
                    value={data.visibility}
                    onChange={(e) => setData('visibility', e.target.value)}
                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-xs text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  >
                    <option value="public">Public (everyone)</option>
                    <option value="authenticated">Authenticated users only</option>
                    <option value="role_restricted">Role-restricted</option>
                  </select>
                </div>

                {data.visibility === 'role_restricted' && (
                  <div>
                    <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-2">
                      Target Roles
                    </p>
                    <div className="space-y-1.5">
                      {['OFW', 'CASE_MANAGER', 'AGENCY', 'ADMIN'].map((role) => (
                        <label key={role} className="flex items-center gap-2">
                          <input
                            type="checkbox"
                            checked={data.target_roles?.includes(role)}
                            onChange={() => toggleRole(role)}
                            className="rounded border-slate-300 text-primary focus:ring-primary"
                          />
                          <span className="text-xs text-slate-700">{role}</span>
                        </label>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-4">
                Tags
              </h3>
              <div className="flex flex-wrap gap-1.5">
                {tags?.map((tag) => (
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
              </div>
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
    </AppLayout>
  );
}
