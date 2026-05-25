import { useEffect, useContext } from 'react';
import { usePage } from '@inertiajs/react';
import { ToastProviderInner, useToast, ToastContext } from '@/Hooks/useToast.jsx';
import AppToast from '@/Components/ui/AppToast';

function ToastStack() {
  const { toasts, removeToast } = useContext(ToastContext);

  if (toasts.length === 0) return null;

  return (
    <div className="fixed top-6 right-6 z-[100] flex flex-col gap-3 max-w-sm w-full pointer-events-none">
      {toasts.map((t) => (
        <div key={t.id} className="pointer-events-auto">
          <AppToast
            message={t.message}
            tone={t.tone}
            exiting={t.exiting}
            onClose={() => removeToast(t.id)}
          />
        </div>
      ))}
    </div>
  );
}

export function FlashMessageWatcher() {
  const toast = useToast();
  const { props } = usePage();

  useEffect(() => {
    const flash = props.flash;
    if (!flash) return;

    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
    if (flash.status) toast.info(flash.status);
  }, [props.flash, toast]);

  return null;
}

export default function ToastProvider({ children }) {
  return (
    <ToastProviderInner>
      <ToastStack />
      {children}
    </ToastProviderInner>
  );
}
