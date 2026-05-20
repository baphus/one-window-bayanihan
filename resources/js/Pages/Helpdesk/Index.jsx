import { Head, Link, usePage } from '@inertiajs/react';
import HelpdeskLayout from '@/Layouts/HelpdeskLayout';
import ArticleCard from '@/Components/Helpdesk/ArticleCard';
import TagBadge from '@/Components/Helpdesk/TagBadge';
import EmptyState from '@/Components/Helpdesk/EmptyState';

export default function Index({ featuredArticles, recentArticles, popularArticles, categories, tags }) {
  const { auth } = usePage().props;
  const user = auth?.user;

  const roleGreeting = {
    OFW: { title: 'OFW Resources', icon: 'badge' },
    CASE_MANAGER: { title: 'Case Manager Guides', icon: 'folder' },
    AGENCY: { title: 'Agency Partner Guides', icon: 'handshake' },
    ADMIN: { title: 'Administration', icon: 'settings' },
  };

  const roleInfo = user ? roleGreeting[user.role] : null;

  return (
    <HelpdeskLayout title="Help Center" categories={categories}>
      <Head>
        <title>Help Center - One Window Bayanihan</title>
      </Head>

      <div className="mb-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-slate-900">How can we help you?</h1>
          <p className="mt-1 text-sm text-slate-500">
            Search our knowledge base for guides, FAQs, and procedures.
          </p>
        </div>

        {roleInfo && (
          <div className="mb-6 rounded-lg border-l-4 border-l-primary bg-blue-50 p-4">
            <div className="flex items-center gap-3">
              <span className="material-symbols-outlined text-2xl text-primary">{roleInfo.icon}</span>
              <div>
                <p className="text-sm font-semibold text-slate-900">{roleInfo.title}</p>
                <p className="text-xs text-slate-500">
                  Quick access to guides tailored for your role.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      {featuredArticles?.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-4 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Featured Articles
          </h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {featuredArticles.map((article) => (
              <ArticleCard key={article.id} article={article} variant="featured" />
            ))}
          </div>
        </section>
      )}

      {categories?.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-4 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Browse by Category
          </h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                href={`/helpdesk?category=${cat.slug}`}
                className="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-primary/30 hover:shadow-md"
              >
                <div className="flex items-center gap-3">
                  {cat.icon && (
                    <span className="material-symbols-outlined text-2xl text-slate-400">
                      {cat.icon}
                    </span>
                  )}
                  <div>
                    <h3 className="text-sm font-semibold text-slate-900 group-hover:text-primary transition-colors">
                      {cat.name}
                    </h3>
                    {cat.published_articles_count > 0 && (
                      <p className="text-xs text-slate-400">
                        {cat.published_articles_count} article{cat.published_articles_count > 1 ? 's' : ''}
                      </p>
                    )}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      <div className="mb-10 grid gap-8 lg:grid-cols-2">
        {recentArticles?.length > 0 && (
          <section>
            <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
              Recently Updated
            </h2>
            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              {recentArticles.map((article) => (
                <ArticleCard key={article.id} article={article} variant="compact" />
              ))}
            </div>
          </section>
        )}

        {popularArticles?.length > 0 && (
          <section>
            <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
              Most Popular
            </h2>
            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              {popularArticles.map((article) => (
                <ArticleCard key={article.id} article={article} variant="compact" />
              ))}
            </div>
          </section>
        )}
      </div>

      {tags?.length > 0 && (
        <section className="mb-10">
          <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
            Browse by Tag
          </h2>
          <div className="flex flex-wrap gap-2">
            {tags.map((tag) => (
              <TagBadge key={tag.id} tag={tag} />
            ))}
          </div>
        </section>
      )}

      {!featuredArticles?.length && !categories?.length && (
        <EmptyState
          icon="menu_book"
          title="Help center is being set up"
          description="Articles will appear here once published. Check back soon!"
        />
      )}
    </HelpdeskLayout>
  );
}
