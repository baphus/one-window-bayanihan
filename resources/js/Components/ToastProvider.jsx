import { useEffect, useRef, useContext, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { ToastProviderInner, ToastContext } from '@/Hooks/useToast.jsx';
import AppToast from '@/Components/ui/AppToast';

function FlashMessageWatcher() {
  const { toast } = useContext(ToastContext);
  const seenRef = useRef(new Set());

  const processFlash = useCallback((flash) => {
    if (!flash) return;
    const key = JSON.stringify(flash);
    if (seenRef.current.has(key)) return;
    seenRef.current.add(key);
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
    if (flash.status) toast.info(flash.status);
  }, [toast]);

  useEffect(() => {
    processFlash(router.page?.props?.flash);
    const cleanup = router.on('navigate', (event) => {
      processFlash(event.detail.page.props.flash);
    });
    return cleanup;
  }, [processFlash]);

  return null;
}

function ToastStack() {
  const { toasts, removeToast } = useContext(ToastContext);

  if (toasts.length === 0) return null;

  return (
    <div className="fixed top-6 right-6 z-[100] flex flex-col gap-3">
      {toasts.map((t) => (
        <AppToast
          key={t.id}
          message={t.message}
          tone={t.tone}
          onClose={() => removeToast(t.id)}
        />
      ))}
    </div>
  );
}

export default function ToastProvider({ children }) {
  return (
    <ToastProviderInner>
      <FlashMessageWatcher />
      <ToastStack />
      {children}
    </ToastProviderInner>
  );
}
