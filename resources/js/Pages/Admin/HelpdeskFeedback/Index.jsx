import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

export default function Index({ articles }) {
  return (
    <AppLayout title="Help Article Feedback">
      <Head title="Help Article Feedback" />
      <div className="mx-auto max-w-6xl">
        <h1 className="mb-2 text-2xl font-bold text-slate-900">Help article feedback</h1>
        <p className="mb-6 text-sm text-slate-600">Current votes submitted or updated within the past 12 months. Anonymous counts are approximate.</p>
        <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs uppercase text-slate-600"><tr><th className="p-3">Article</th><th className="p-3 text-right">Yes</th><th className="p-3 text-right">No</th><th className="p-3 text-right">Helpful</th></tr></thead>
            <tbody>{articles.map((article) => <tr key={article.slug} className="border-t border-slate-100"><td className="p-3 font-medium text-slate-900">{article.title}</td><td className="p-3 text-right">{article.yes}</td><td className="p-3 text-right">{article.no}</td><td className="p-3 text-right">{article.percent === null ? '—' : `${article.percent}%`} ({article.total} votes)</td></tr>)}</tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
