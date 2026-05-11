import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';

export default function SystemSettings() {
  return (
    <AppLayout title="System Settings">
      <Head title="System Settings" />
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-slate-900">System Settings</h1>
        <p className="text-sm text-slate-500 mt-1">Manage system-wide configuration and preferences.</p>
      </div>

      <div className="grid grid-cols-1 gap-6 max-w-2xl">
        <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
          <h3 className="text-base font-semibold text-slate-900 mb-4">Application Information</h3>
          <dl className="space-y-3 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">Application Name</dt>
              <dd className="font-medium text-slate-900">One Window Bayanihan</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Version</dt>
              <dd className="font-medium text-slate-900">1.0.0</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Region</dt>
              <dd className="font-medium text-slate-900">Central Visayas (Region VII)</dd>
            </div>
          </dl>
        </div>

        <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
          <h3 className="text-base font-semibold text-slate-900 mb-4">SERVQUAL Configuration</h3>
          <p className="text-sm text-slate-600">
            SERVQUAL (Service Quality) dimensions and parameters are used to measure client satisfaction across agencies.
            Configuration management will be available in a future update.
          </p>
        </div>

        <div className="rounded-lg bg-white shadow-sm border border-slate-200 p-6">
          <h3 className="text-base font-semibold text-slate-900 mb-4">OTP Settings</h3>
          <p className="text-sm text-slate-600">
            One-Time Password settings for the public case tracking system. Currently using database-backed OTP storage.
            SMS integration will be available in a future update.
          </p>
        </div>
      </div>
    </AppLayout>
  );
}
