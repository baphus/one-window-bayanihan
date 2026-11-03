import { useState, useMemo, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import useMfaStatus from '@/Hooks/useMfaStatus';
import ConfirmDialog from '@/Components/ui/ConfirmDialog';
import MfaConfirmDialog from '@/Components/MfaConfirmDialog';

const isMfaSetting = (setting) => /^mfa_role_(case_manager|agency|ofw)_required$/.test(setting?.key ?? '');
function settingInputValue(setting) {
  return setting.type === 'bool' ? Boolean(setting.value) : String(setting.value ?? '');
}

function toPayload(setting, value) {
  if (setting.type === 'bool') return value;
  if (setting.type === 'int') return value === '' ? null : Number(value);
  return value;
}

function SettingField({ setting, value, onChange }) {
  const isBool = setting.type === 'bool';
  const mfa = isMfaSetting(setting);

  return (
    <div className="py-4 border-b border-slate-100 last:border-0 last:pb-0 first:pt-0">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <label htmlFor={`setting-${setting.key}`} className="block text-sm font-medium text-slate-900">
            {setting.description}
          </label>
          <p className="mt-1 text-xs text-slate-400">
            <code>{setting.key}</code>
            {setting.source === 'default' && setting.env_fallback && (
              <> — currently from <code>{setting.env_fallback}</code>; changing it here overrides the environment.</>
            )}
            {setting.source === 'database' && setting.env_fallback && (
              <> — overriding <code>{setting.env_fallback}</code>.</>
            )}
          </p>
        </div>

        <div className="shrink-0">
          {isBool ? (
            <label className="inline-flex cursor-pointer items-center gap-2">
              <input
                id={`setting-${setting.key}`}
                type="checkbox"
                checked={value}
                onChange={(e) => onChange(e.target.checked)}
                className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span className="text-sm text-slate-600">
                {mfa ? (value ? 'Required' : 'Optional') : (value ? 'On' : 'Off')}
              </span>
            </label>
          ) : (
            <input
              id={`setting-${setting.key}`}
              type="number"
              min={setting.bounds?.min}
              max={setting.bounds?.max}
              value={value}
              onChange={(e) => onChange(e.target.value)}
              className="w-28 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            />
          )}
        </div>
      </div>
    </div>
  );
}

export default function SystemSettings({
  groups = [],
  readOnly = [],
  errors = {},
}) {
  const { status: mfaStatus } = useMfaStatus();
  const pendingReasonRef = useRef('');

  // Flatten declared settings into one editable map.
  const initial = useMemo(() => {
    const map = {};
    groups.forEach((group) => {
      group.settings.forEach((setting) => {
        map[setting.key] = settingInputValue(setting);
      });
    });
    return map;
  }, [groups]);

  const [values, setValues] = useState(initial);
  const [pendingConfirm, setPendingConfirm] = useState(null);
  const [mfaConfirmOpen, setMfaConfirmOpen] = useState(false);
  const [forceMfaProof, setForceMfaProof] = useState(false);
  const [mfaProofError, setMfaProofError] = useState('');

  const changedKeys = useMemo(
    () => Object.keys(values).filter((key) => values[key] !== initial[key]),
    [values, initial],
  );

  const hasDirty = changedKeys.length > 0;
  const { UnsavedModal, bypassNext } = useUnsavedChanges(hasDirty);

  const settingByKey = useMemo(
    () => Object.fromEntries(groups.flatMap((group) => group.settings).map((setting) => [setting.key, setting])),
    [groups],
  );

  const mfaChangedKeys = useMemo(
    () => changedKeys.filter((key) => isMfaSetting(settingByKey[key] ?? { key })),
    [changedKeys, settingByKey],
  );

  // Keys that require a confirmation step before they can be saved.
  const confirmKeys = useMemo(() => {
    const keys = new Set();
    groups.forEach((group) => {
      group.settings.forEach((setting) => {
        if (setting.confirm_required) keys.add(setting.key);
      });
    });
    return keys;
  }, [groups]);

  const otherConfirmKeys = changedKeys.filter((key) => confirmKeys.has(key) && !mfaChangedKeys.includes(key));

  const submit = (confirmed, reason = '', proof = {}) => {
    const payload = { confirmed: Boolean(confirmed), ...proof };
    if (mfaChangedKeys.length > 0) payload.mfa_change_reason = reason;
    if (pendingReasonRef.current && !reason) payload.mfa_change_reason = pendingReasonRef.current;
    changedKeys.forEach((key) => {
      payload[key] = toPayload(settingByKey[key], values[key]);
    });

    bypassNext();
    router.post(route('admin.system-settings.update'), payload, {
      preserveScroll: true,
      onSuccess: () => {
        setPendingConfirm(null);
        pendingReasonRef.current = '';
      },
      onError: (errs) => {
        setPendingConfirm(null);
        if (errs?.mfa_confirmation) {
          setForceMfaProof(true);
          setMfaProofError(errs.mfa_confirmation?.[0] ?? errs.mfa_confirmation);
          setMfaConfirmOpen(true);
        }
      },
    });
  };

  const handleSave = () => {
    if (!hasDirty) return;
    if (mfaChangedKeys.length > 0) {
      setForceMfaProof(false);
      setMfaProofError('');
      setMfaConfirmOpen(true);
      return;
    }
    if (otherConfirmKeys.length > 0) {
      setPendingConfirm(otherConfirmKeys);
      return;
    }
    submit(false);
  };

  const handleMfaConfirmed = ({ reason, proof }) => {
    pendingReasonRef.current = reason;
    setMfaConfirmOpen(false);
    setForceMfaProof(false);
    setMfaProofError('');
    submit(otherConfirmKeys.length > 0, reason, proof || {});
  };

  return (
    <AppLayout title="System Settings">
      <Head title="System Settings" />

      <div data-tour="settings-header" className="mb-8">
        <h1 className="text-2xl md:text-3xl font-extrabold font-headline tracking-tight text-slate-900">System Settings</h1>
        <p className="text-sm text-slate-400 font-body mt-0.5">
          System-wide configuration. Every change is recorded in the audit log.
        </p>
      </div>

      {errors?.confirmed && (
        <div className="mb-6 max-w-3xl rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {errors.confirmed}
        </div>
      )}

      <div data-tour="settings-form" className="max-w-3xl space-y-6">
        {groups.map((group) => (
          <section
            key={group.key}
            className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            aria-labelledby={`group-${group.key}`}
          >
            <h2 id={`group-${group.key}`} className="text-base font-semibold text-slate-900">{group.label}</h2>
            {group.description && (
              <p className="mt-1 text-sm text-slate-500">{group.description}</p>
            )}
            {group.settings.some(isMfaSetting) && (
              <p className="mt-2 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                Optional enrollment still challenges anyone who is already enrolled, and never deletes
                existing authenticator or recovery codes. Administrator accounts are always required.
              </p>
            )}

            <div className="mt-4">
              {group.settings.map((setting) => (
                <SettingField
                  key={setting.key}
                  setting={setting}
                  value={values[setting.key]}
                  onChange={(next) => setValues((prev) => ({ ...prev, [setting.key]: next }))}
                />
              ))}
            </div>
          </section>
        ))}

        {/* Values the deployment controls. Named rather than hidden so nobody
            hunts for a field that will never exist. */}
        {(readOnly.length > 0) && (
          <section className="rounded-lg border border-slate-200 bg-slate-50 p-6" aria-labelledby="group-readonly">
            <h2 id="group-readonly" className="text-base font-semibold text-slate-900">Fixed by the deployment</h2>
            <p className="mt-1 text-sm text-slate-500">
              These cannot be changed here. Secrets and boot-time configuration stay in the environment.
            </p>

            <dl className="mt-4 space-y-3 text-sm">
              {readOnly.map((item) => (
                <div key={item.key} className="flex flex-wrap justify-between gap-2">
                  <dt className="text-slate-600">
                    {item.note} <code className="text-xs text-slate-400">{item.env}</code>
                  </dt>
                  <dd className="font-medium text-slate-900">{item.value || 'Not set'}</dd>
                </div>
              ))}
            </dl>
          </section>
        )}

        <div className="flex items-center gap-3">
          <button
            onClick={handleSave}
            disabled={!hasDirty}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
          >
            Save changes
          </button>
          {hasDirty && (
            <span className="text-xs text-slate-500">
              {changedKeys.length} unsaved {changedKeys.length === 1 ? 'change' : 'changes'}
            </span>
          )}
        </div>

      </div>

      <ConfirmDialog
        open={pendingConfirm !== null}
        title="Change retention settings"
        message={`You are changing ${pendingConfirm?.join(', ')}. Lowering a retention period means the next scheduled cleanup will permanently delete more than it would have.`}
        confirmLabel="Change anyway"
        cancelLabel="Cancel"
        tone="danger"
        onConfirm={() => submit(true, pendingReasonRef.current, {})}
        onCancel={() => setPendingConfirm(null)}
      />

      <MfaConfirmDialog
        open={mfaConfirmOpen}
        title="Confirm MFA policy change"
        description="MFA policy changes need a reason and your own fresh MFA confirmation."
        confirmLabel="Save policy"
        reasonRequired
        initialReason={pendingReasonRef.current}
        confirmedUntil={forceMfaProof ? null : mfaStatus?.confirmed_until}
        forceProof={forceMfaProof}
        error={mfaProofError}
        onCancel={() => {
          setMfaConfirmOpen(false);
          setForceMfaProof(false);
          setMfaProofError('');
        }}
        onConfirmed={({ reason, proof }) => {
          pendingReasonRef.current = reason;
          handleMfaConfirmed({ reason, proof });
        }}
      />

      {UnsavedModal}
    </AppLayout>
  );
}
