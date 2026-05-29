import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';

export default function Index({ tasks = [] }) {
  const toggleTask = (task) => {
    router.post(route('admin.system.scheduled-tasks.toggle', task.command), {}, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout title="Scheduled Tasks">
      <Head title="Scheduled Tasks" />

      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Scheduled Tasks</h1>
        <p className="mt-1 text-sm text-slate-500">Enable or disable background scheduled commands.</p>
      </div>

      <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-slate-200">
          <thead className="bg-slate-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Command</th>
              <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Description</th>
              <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Interval</th>
              <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
              <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Last Run</th>
              <th className="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200 bg-white">
            {tasks.map((task) => (
              <tr key={task.command}>
                <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{task.command}</td>
                <td className="px-6 py-4 text-sm text-slate-600">{task.description}</td>
                <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{task.interval}</td>
                <td className="whitespace-nowrap px-6 py-4 text-sm">
                  <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${task.enabled ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600'}`}>
                    {task.enabled ? 'Enabled' : 'Disabled'}
                  </span>
                </td>
                <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{task.last_run ?? '—'}</td>
                <td className="whitespace-nowrap px-6 py-4 text-right text-sm">
                  <button
                    onClick={() => toggleTask(task)}
                    className="rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                  >
                    Toggle
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
