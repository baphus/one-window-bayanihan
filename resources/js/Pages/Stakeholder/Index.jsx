import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

function AgencyLogo({ agency }) {
  const [hasError, setHasError] = useState(false);

  if (hasError || !agency.logo_url) {
    return (
      <div className="w-12 h-12 bg-blue-100 rounded flex items-center justify-center">
        <span className="text-lg font-bold text-blue-900">{agency.short?.charAt(0)}</span>
      </div>
    );
  }

  return (
    <img
      src={agency.logo_url}
      alt={agency.short}
      className="w-12 h-12 object-contain rounded"
      referrerPolicy="no-referrer"
      loading="lazy"
      onError={() => setHasError(true)}
    />
  );
}

export default function StakeholderIndex({ agencies }) {
  return (
    <AppLayout title="Stakeholders">
      <Head title="Stakeholders" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">Stakeholders</h1>
        <p className="text-sm text-slate-500 mt-1">Partner agencies and organizations involved in the One Window Bayanihan system.</p>
      </div>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {agencies.map((agency) => (
          <div key={agency.id} className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
            <div className="flex items-center gap-4 mb-4">
              <AgencyLogo agency={agency} />
              <div>
                <h3 className="text-base font-semibold text-slate-900">{agency.short}</h3>
                <p className="text-xs text-slate-500">{agency.name}</p>
              </div>
            </div>

            <div className="space-y-2 text-sm">
              <p className="text-slate-600">
                <span className="font-medium text-slate-900">Services:</span>{' '}
                {agency.services?.length ?? 0}
              </p>
              <p className="text-slate-600">
                <span className="font-medium text-slate-900">Active Referrals:</span>{' '}
                {agency.referrals?.filter(r => r.status !== 'COMPLETED' && r.status !== 'REJECTED')?.length ?? 0}
              </p>
            </div>

            {agency.services?.length > 0 && (
              <div className="mt-4 pt-4 border-t border-slate-100">
                <p className="text-xs font-medium text-slate-500 uppercase tracking-wider mb-2">Services Offered</p>
                <div className="flex flex-wrap gap-1">
                  {agency.services.map((svc) => (
                    <span key={svc.id} className="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700 border border-blue-100">
                      {svc.name}
                    </span>
                  ))}
                </div>
              </div>
            )}

            <div className="mt-4 pt-4 border-t border-slate-100">
              <Link
                href={route('stakeholders.show', agency.id)}
                className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-900"
              >
                View Details
                <span className="material-symbols-outlined text-[14px]">chevron_right</span>
              </Link>
            </div>
          </div>
        ))}
      </div>
    </AppLayout>
  );
}
