import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

const CHECK_META = {
  queue: { label: 'Queue', icon: 'database' },
  cache: { label: 'Cache', icon: 'bolt' },
  disk: { label: 'Disk', icon: 'hard_drive' },
  database: { label: 'Database', icon: 'dns' },
};

const STATUS_STYLES = {
  healthy: 'bg-green-100 text-green-800 border-green-200',
  warning: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  critical: 'bg-red-100 text-red-800 border-red-200',
  unknown: 'bg-slate-100 text-slate-600 border-slate-200',
};

export default function Index({ overview }) {
  const checks = overview?.checks ?? [];
  const checksByType = Object.fromEntries(checks.map((check) => [check.check_type, check]));

  const cards = Object.keys(CHECK_META).map((type) => {
    const meta = CHECK_META[type];
    const check = checksByType[type];

    return {
      type,
      label: meta.label,
      icon: meta.icon,
      status: check?.status ?? 'unknown',
      metric: check?.metric_value ?? '—',
      message: check?.message ?? 'No health data yet.',
    };
  });

  const overall = overview?.overall ?? 'healthy';

  const runChecks = () => {
    router.post(route('admin.system.health.run-checks'), {}, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="System Health Dashboard">
      <Head title="System Health Dashboard" />

      <div className="mb-8 flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">System Health Dashboard</h1>
          <p className="mt-1 text-sm text-slate-500">Monitor queue, cache, disk, and database status.</p>
        </div>

        <button
          onClick={runChecks}
          className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
        >
          Run Health Checks
        </button>
      </div>

      <div className="mb-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-center justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-slate-500">Overall Status</p>
            <div className="mt-2 flex items-center gap-3">
              <span className={`inline-flex rounded-full border px-3 py-1 text-sm font-semibold capitalize ${STATUS_STYLES[overall] ?? STATUS_STYLES.unknown}`}>
                {overall}
              </span>
              <span className="text-sm text-slate-500">Latest health scan summary</span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <div key={card.type} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
              <div>
                <div className="flex items-center gap-2 text-slate-900">
                  <span className="material-symbols-outlined text-xl text-slate-500">{card.icon}</span>
                  <h2 className="text-base font-semibold">{card.label}</h2>
                </div>
                <p className="mt-2 text-2xl font-bold text-slate-900">{card.metric}</p>
              </div>
              <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${STATUS_STYLES[card.status] ?? STATUS_STYLES.unknown}`}>
                {card.status}
              </span>
            </div>
            <p className="mt-4 text-sm leading-6 text-slate-500">{card.message}</p>
          </div>
        ))}
      </div>
    </AppLayout>
  );
}
