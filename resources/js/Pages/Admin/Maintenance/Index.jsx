import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ status }) {
  const [secret, setSecret] = useState('');
  const [retryMinutes, setRetryMinutes] = useState('');

  const isActive = !!status?.active;

  const toggle = () => {
    router.post(route('admin.system.maintenance.toggle'), isActive ? {} : {
      secret: secret || null,
      retry_minutes: retryMinutes || null,
    }, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Maintenance Mode">
      <Head title="Maintenance Mode" />

      <div className="max-w-3xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Maintenance Mode</h1>
          <p className="mt-1 text-sm text-slate-500">Control site downtime for planned maintenance.</p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-medium text-slate-500">Current Status</p>
              <span className={`mt-2 inline-flex rounded-full border px-3 py-1 text-sm font-semibold ${isActive ? 'border-red-200 bg-red-100 text-red-800' : 'border-green-200 bg-green-100 text-green-800'}`}>
                {isActive ? 'Active' : 'Inactive'}
              </span>
            </div>
            <div className="text-right text-sm text-slate-500">
              {isActive ? `Since ${status?.since ?? 'unknown'}` : 'Site is currently available.'}
            </div>
          </div>

          {isActive ? (
            <div className="mt-6 space-y-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <p className="font-semibold">Warning: disabling maintenance mode will reopen the site.</p>
              <div className="grid gap-2 sm:grid-cols-2">
                <div><span className="font-medium">Secret:</span> {status?.secret ?? 'None'}</div>
                <div><span className="font-medium">Retry:</span> {status?.retry ? `${status.retry} seconds` : 'None'}</div>
              </div>
              <button
                onClick={toggle}
                className="rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-red-500"
              >
                Disable Maintenance Mode
              </button>
            </div>
          ) : (
            <div className="mt-6 space-y-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <p className="font-semibold">Warning: enabling maintenance mode will make the site unavailable to most users.</p>
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block">
                  <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-900">Secret (optional)</span>
                  <input
                    value={secret}
                    onChange={(e) => setSecret(e.target.value)}
                    className="w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-slate-900 focus:border-amber-500 focus:outline-none"
                    placeholder="e.g. bypass123"
                  />
                </label>
                <label className="block">
                  <span className="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-900">Retry minutes (optional)</span>
                  <input
                    type="number"
                    min="1"
                    max="1440"
                    value={retryMinutes}
                    onChange={(e) => setRetryMinutes(e.target.value)}
                    className="w-full rounded-md border border-amber-300 bg-white px-3 py-2 text-slate-900 focus:border-amber-500 focus:outline-none"
                    placeholder="60"
                  />
                </label>
              </div>
              <button
                onClick={toggle}
                className="rounded-md bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-800"
              >
                Enable Maintenance Mode
              </button>
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
