import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ articles, filters, categories }) {
  const { auth } = usePage().props;
  const articleList = articles?.data ?? [];
  const pagination = articles ?? {};

  const [search, setSearch] = useState(filters?.search || '');

  const handleSearch = (e) => {
    e.preventDefault();
    router.get(route('admin.helpdesk.articles.index'), { search }, { preserveState: true });
  };

  const filterByStatus = (status) => {
    router.get(route('admin.helpdesk.articles.index'), {
      ...filters,
      status: status || undefined,
      trashed: undefined,
    }, { preserveState: true });
  };

  const toggleFeatured = (id) => {
    router.post(route('admin.helpdesk.articles.toggle-featured', id), {}, {
      preserveScroll: true,
    });
  };

  const deleteArticle = (id) => {
    if (confirm('Move this article to trash?')) {
      router.delete(route('admin.helpdesk.articles.destroy', id), {
        preserveScroll: true,
      });
    }
  };

  const restoreArticle = (id) => {
    router.post(route('admin.helpdesk.articles.restore', id), {}, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Helpdesk Articles">
      <Head title="Helpdesk Articles" />

      <div className="mb-8 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Helpdesk Articles</h1>
          <p className="text-sm text-slate-500 mt-1">Manage the knowledge base articles.</p>
        </div>
        <Link
          href={route('admin.helpdesk.articles.create')}
          className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
        >
          New Article
        </Link>
      </div>

      <div className="mb-6 flex items-center gap-4">
        <form onSubmit={handleSearch} className="flex gap-2">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search articles..."
            className="rounded-md border border-slate-300 px-3 py-2 text-xs text-slate-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <button
            type="submit"
            className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white"
          >
            Search
          </button>
        </form>

        <div className="flex gap-1">
          {['', 'draft', 'published'].map((status) => (
            <button
              key={status}
              onClick={() => filterByStatus(status)}
              className={`rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wider ${
                (status === '' && !filters?.status) || filters?.status === status
                  ? 'bg-primary text-white'
                  : 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-100'
              }`}
            >
              {status || 'All'}
            </button>
          ))}
        </div>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Title</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Category</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Featured</th>
              <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Updated</th>
              <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200">
            {articleList.map((article) => (
              <tr key={article.id} className="hover:bg-slate-50">
                <td className="px-6 py-4">
                  <Link
                    href={route('admin.helpdesk.articles.edit', article.id)}
                    className="text-sm font-semibold text-slate-800 hover:text-primary"
                  >
                    {article.title}
                  </Link>
                  <div className="mt-0.5 text-xs text-slate-400">
                    /helpdesk/{article.slug}
                  </div>
                </td>
                <td className="px-6 py-4 text-xs text-slate-500">
                  {article.category?.name || '-'}
                </td>
                <td className="px-6 py-4">
                  <span
                    className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                      article.status === 'published'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800'
                    }`}
                  >
                    {article.status}
                  </span>
                </td>
                <td className="px-6 py-4">
                  <button
                    onClick={() => toggleFeatured(article.id)}
                    className={`text-sm ${article.featured ? 'text-yellow-500' : 'text-slate-300'}`}
                  >
                    <span className="material-symbols-outlined">star</span>
                  </button>
                </td>
                <td className="px-6 py-4 text-xs text-slate-500">
                  {new Date(article.updated_at).toLocaleDateString()}
                </td>
                <td className="px-6 py-4 text-right">
                  <div className="flex items-center justify-end gap-2">
                    <Link
                      href={route('admin.helpdesk.articles.edit', article.id)}
                      className="text-xs font-semibold text-primary hover:underline"
                    >
                      Edit
                    </Link>
                    <Link
                      href={route('admin.helpdesk.articles.versions', article.id)}
                      className="text-xs font-semibold text-slate-500 hover:underline"
                    >
                      Versions
                    </Link>
                    <button
                      onClick={() => deleteArticle(article.id)}
                      className="text-xs font-semibold text-red-600 hover:underline"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {articleList.length === 0 && (
          <div className="py-12 text-center">
            <span className="material-symbols-outlined text-4xl text-slate-300 mb-3">article</span>
            <p className="text-sm text-slate-500">No articles found.</p>
          </div>
        )}
      </div>

      {pagination?.last_page > 1 && (
        <div className="mt-6 flex items-center justify-center gap-2">
          {pagination.links?.map((link, i) => (
            <Link
              key={i}
              href={link.url || '#'}
              dangerouslySetInnerHTML={{ __html: link.label }}
              className={`rounded-md px-3 py-1.5 text-xs font-semibold ${
                link.active
                  ? 'bg-primary text-white'
                  : link.url
                  ? 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-100'
                  : 'text-slate-300 cursor-default'
              }`}
            />
          ))}
        </div>
      )}
    </AppLayout>
  );
}
