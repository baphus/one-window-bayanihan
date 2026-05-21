import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { UnifiedTable } from '@/Components/ui/UnifiedTable';

export default function Index({ articles, filters, categories }) {
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

  function paginatorProps(paginator) {
    return {
      totalRecords: paginator.total,
      startIndex: paginator.from,
      endIndex: paginator.to,
      currentPage: paginator.current_page,
      totalPages: paginator.last_page,
      rowsPerPage: paginator.per_page,
      hideControlBar: true,
      onPageChange: (page) => {
        router.get(route('admin.helpdesk.articles.index'), { ...filters, page }, { preserveState: true });
      },
      onRowsPerPageChange: (n) => {
        router.get(route('admin.helpdesk.articles.index'), { ...filters, per_page: n, page: undefined }, { preserveState: true });
      },
    };
  }

  const columns = useMemo(() => [
    {
      key: 'title',
      title: 'Title',
      sortable: true,
      render: (row) => (
        <div>
          <Link
            href={route('admin.helpdesk.articles.edit', row.id)}
            className="text-sm font-semibold text-slate-800 hover:text-primary"
          >
            {row.title}
          </Link>
          <div className="mt-0.5 text-xs text-slate-400">
            /helpdesk/{row.slug}
          </div>
        </div>
      ),
    },
    {
      key: 'category',
      title: 'Category',
      sortable: true,
      render: (row) => row.category?.name || '-',
    },
    {
      key: 'status',
      title: 'Status',
      sortable: true,
      render: (row) => (
        <span
          className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
            row.status === 'published'
              ? 'bg-green-100 text-green-800'
              : 'bg-yellow-100 text-yellow-800'
          }`}
        >
          {row.status}
        </span>
      ),
    },
    {
      key: 'featured',
      title: 'Featured',
      sortable: false,
      render: (row) => (
        <button
          onClick={() => toggleFeatured(row.id)}
          className={`text-sm ${row.featured ? 'text-yellow-500' : 'text-slate-300'}`}
        >
          <span className="material-symbols-outlined">star</span>
        </button>
      ),
    },
    {
      key: 'updated_at',
      title: 'Updated',
      sortable: true,
      render: (row) => new Date(row.updated_at).toLocaleDateString(),
    },
    {
      key: 'id',
      title: 'Actions',
      sortable: false,
      render: (row) => (
        <div className="flex items-center justify-end gap-2">
          <Link
            href={route('admin.helpdesk.articles.edit', row.id)}
            className="text-xs font-semibold text-primary hover:underline"
          >
            Edit
          </Link>
          <Link
            href={route('admin.helpdesk.articles.versions', row.id)}
            className="text-xs font-semibold text-slate-500 hover:underline"
          >
            Versions
          </Link>
          <button
            onClick={() => deleteArticle(row.id)}
            className="text-xs font-semibold text-red-600 hover:underline"
          >
            Delete
          </button>
        </div>
      ),
    },
  ], []);

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

      <UnifiedTable
        columns={columns}
        data={articleList}
        keyExtractor={(row) => row.id}
        {...paginatorProps(pagination)}
      />
    </AppLayout>
  );
}
