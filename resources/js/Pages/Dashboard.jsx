import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

function StatCard({ label, value, color = 'text-gray-900' }) {
  return (
    <div className="rounded-lg bg-white p-6 shadow-sm border border-slate-200">
      <p className="text-sm font-medium text-slate-500">{label}</p>
      <p className={`mt-2 text-3xl font-bold ${color}`}>{value}</p>
    </div>
  );
}

export default function Dashboard(props) {
  const { role, recentCases, recentReferrals, recentLogs, ...stats } = props;

  if (role === 'AGENCY') {
    return (
      <AppLayout title="Dashboard">
        <Head title="Dashboard" />
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Agency Dashboard</h1>
          <p className="text-sm text-slate-500 mt-1">Overview of your agency's referrals and performance.</p>
        </div>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
          <StatCard label="Total Referrals" value={stats.totalReferrals} color="text-blue-900" />
          <StatCard label="Pending" value={stats.pendingReferrals} color="text-yellow-600" />
          <StatCard label="Processing" value={stats.processingReferrals} color="text-blue-600" />
          <StatCard label="Completed" value={stats.completedReferrals} color="text-green-600" />
        </div>

        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
          <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 className="text-base font-semibold text-slate-900">Recent Referrals</h3>
            <Link href={route('referrals.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Service</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {recentReferrals?.length === 0 ? (
                  <tr><td colSpan={4} className="px-6 py-4 text-center text-sm text-slate-500">No referrals yet.</td></tr>
                ) : (
                  recentReferrals?.map((ref) => (
                    <tr key={ref.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{ref.case_file?.case_number ?? 'N/A'}</td>
                      <td className="px-6 py-4 text-sm text-slate-500">
                        {ref.case_file?.client ? `${ref.case_file.client.first_name} ${ref.case_file.client.last_name}` : 'N/A'}
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-500">{ref.required_services}</td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                          ref.status === 'COMPLETED' ? 'bg-green-100 text-green-800' :
                          ref.status === 'PROCESSING' ? 'bg-blue-100 text-blue-800' :
                          ref.status === 'REJECTED' ? 'bg-red-100 text-red-800' :
                          ref.status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-slate-100 text-slate-800'
                        }`}>{ref.status}</span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </AppLayout>
    );
  }

  if (role === 'ADMIN') {
    return (
      <AppLayout title="Dashboard">
        <Head title="Dashboard" />
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
          <p className="text-sm text-slate-500 mt-1">System-wide overview and monitoring.</p>
        </div>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
          <StatCard label="Total Cases" value={stats.totalCases} />
          <StatCard label="Total Referrals" value={stats.totalReferrals} />
          <StatCard label="Total Users" value={stats.totalUsers} />
          <StatCard label="Agencies" value={stats.totalAgencies} />
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div className="rounded-lg bg-white shadow-sm border border-slate-200">
            <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
              <h3 className="text-base font-semibold text-slate-900">Recent Cases</h3>
              <Link href={route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Created</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200">
                  {recentCases?.length === 0 ? (
                    <tr><td colSpan={3} className="px-6 py-4 text-center text-sm text-slate-500">No cases yet.</td></tr>
                  ) : (
                    recentCases?.map((c) => (
                      <tr key={c.id} className="hover:bg-slate-50">
                        <td className="px-6 py-4 text-sm font-medium text-slate-900">{c.case_number}</td>
                        <td className="px-6 py-4">
                          <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${c.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>{c.status}</span>
                        </td>
                        <td className="px-6 py-4 text-sm text-slate-500">{new Date(c.created_at).toLocaleDateString()}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          <div className="rounded-lg bg-white shadow-sm border border-slate-200">
            <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
              <h3 className="text-base font-semibold text-slate-900">Recent Activity</h3>
              <Link href={route('audit-logs.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
            </div>
            <div className="divide-y divide-slate-200">
              {recentLogs?.length === 0 ? (
                <p className="px-6 py-4 text-sm text-slate-500">No recent activity.</p>
              ) : (
                recentLogs?.map((log) => (
                  <div key={log.id} className="px-6 py-3">
                    <p className="text-sm text-slate-900">
                      <span className="font-medium">{log.user?.name ?? 'System'}</span>
                      {' '}{log.action} {log.module}
                    </p>
                    <p className="text-xs text-slate-500 mt-0.5">{new Date(log.timestamp).toLocaleString()}</p>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout title="Dashboard">
      <Head title="Dashboard" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Case Manager Dashboard</h1>
        <p className="text-sm text-slate-500 mt-1">Overview of cases, referrals, and agencies.</p>
      </div>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <StatCard label="Total Cases" value={stats.totalCases} color="text-blue-900" />
        <StatCard label="Open Cases" value={stats.openCases} color="text-green-600" />
        <StatCard label="Pending Referrals" value={stats.pendingReferrals} color="text-yellow-600" />
        <StatCard label="Active Agencies" value={stats.activeAgencies} color="text-purple-600" />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
          <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 className="text-base font-semibold text-slate-900">Recent Cases</h3>
            <Link href={route('cases.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Client</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Created</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {recentCases?.length === 0 ? (
                  <tr><td colSpan={4} className="px-6 py-4 text-center text-sm text-slate-500">No cases yet.</td></tr>
                ) : (
                  recentCases?.map((c) => (
                    <tr key={c.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{c.case_number}</td>
                      <td className="px-6 py-4 text-sm text-slate-500">{c.client ? `${c.client.first_name} ${c.client.last_name}` : 'N/A'}</td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${c.status === 'OPEN' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'}`}>{c.status}</span>
                      </td>
                      <td className="px-6 py-4 text-sm text-slate-500">{new Date(c.created_at).toLocaleDateString()}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
          <div className="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 className="text-base font-semibold text-slate-900">Recent Referrals</h3>
            <Link href={route('referrals.index')} className="text-sm text-indigo-600 hover:text-indigo-900">View All</Link>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Case #</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Agency</th>
                  <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {recentReferrals?.length === 0 ? (
                  <tr><td colSpan={3} className="px-6 py-4 text-center text-sm text-slate-500">No referrals yet.</td></tr>
                ) : (
                  recentReferrals?.map((ref) => (
                    <tr key={ref.id} className="hover:bg-slate-50">
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{ref.case_file?.case_number ?? 'N/A'}</td>
                      <td className="px-6 py-4 text-sm text-slate-500">{ref.agency?.name ?? 'N/A'}</td>
                      <td className="px-6 py-4">
                        <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                          ref.status === 'COMPLETED' ? 'bg-green-100 text-green-800' :
                          ref.status === 'PROCESSING' ? 'bg-blue-100 text-blue-800' :
                          ref.status === 'REJECTED' ? 'bg-red-100 text-red-800' :
                          ref.status === 'PENDING' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-slate-100 text-slate-800'
                        }`}>{ref.status}</span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
