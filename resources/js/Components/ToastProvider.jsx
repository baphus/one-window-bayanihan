import { useEffect, useContext, useState, useCallback, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useToast, ToastContext } from '@/Hooks/useToast.jsx';
import AppToast from '@/Components/ui/AppToast';

let toastId = 0;

const EXIT_ANIMATION_MS = 300;

function ToastStack() {
  const { toasts, removeToast } = useContext(ToastContext);

  if (toasts.length === 0) return null;

  return (
    <div className="pointer-events-none fixed left-4 right-4 top-4 z-[100] flex flex-col gap-2 sm:left-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-sm">
      {toasts.slice(0, 3).map((t) => (
        <div key={t.id} className="pointer-events-auto">
          <AppToast
            message={t.message}
            tone={t.tone}
            exiting={t.exiting}
            duration={t.duration}
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
    const removeValidationListener = router.on('error', (event) => {
      const first = Object.values(event.detail.errors).flat().find((message) => typeof message === 'string' && message.trim());
      toast.error(first ? `Please review the form: ${first}` : 'Please review the highlighted fields.');
    });
    const removeRequestListener = router.on('exception', () => {
      toast.error('Could not complete request. Check your connection and try again.');
    });
    const removeHttpErrorListener = router.on('invalid', (event) => {
      const { status, data } = event.detail.response;
      event.preventDefault();
      if (status === 422 && data && typeof data === 'object' && data.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat().find((message) => typeof message === 'string' && message.trim());
        toast.error(first ? `Please review the form: ${first}` : 'Please review the highlighted fields.');
        return;
      }
      const message = status === 401
        ? 'Your session expired. Sign in again, then retry your action.'
        : status === 403
          ? 'Access denied. Your account may not have permission for this action.'
          : status >= 500
            ? 'Server error. Please try again later. No changes were retried.'
            : 'Request failed. Please try again.';
      toast.error(message);
    });
    return () => {
      removeValidationListener();
      removeRequestListener();
      removeHttpErrorListener();
    };
  }, [toast]);

  useEffect(() => {
    const flash = props.flash;
    if (!flash) return;

    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
    if (flash.status) toast.info(flash.status);

    if (flash.mfa_recovery_codes_remaining) {
      const count = Number(flash.mfa_recovery_codes_remaining);
      if (count <= 3) {
        toast.warning(
          `You have ${count} recovery code${count === 1 ? '' : 's'} remaining. Consider generating new codes.`,
          8000,
        );
      } else {
        toast.info(
          `You have ${count} recovery code${count === 1 ? '' : 's'} remaining.`,
          6000,
        );
      }
    }
  }, [props.flash, toast]);

  return null;
}

export default function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const exitTimers = useRef({});

  useEffect(() => () => {
    Object.values(exitTimers.current).forEach(clearTimeout);
  }, []);

  const removeToast = useCallback((id) => {
    if (exitTimers.current[id]) return;
    setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, exiting: true } : t)));
    exitTimers.current[id] = setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
      delete exitTimers.current[id];
    }, EXIT_ANIMATION_MS);
  }, []);

  const addToast = useCallback((message, tone = 'info', duration) => {
    const id = ++toastId;
    const defaultDuration = tone === 'warning' || tone === 'error' ? 8000 : 5000;
    setToasts((prev) => [...prev, { id, message, tone, duration: duration ?? defaultDuration, exiting: false }]);
    return id;
  }, []);

  const toast = useCallback(
    (message, tone = 'info', duration) => addToast(message, tone, duration),
    [addToast],
  );

  toast.success = useCallback(
    (msg, duration) => addToast(msg, 'success', duration),
    [addToast],
  );

  toast.error = useCallback(
    (msg, duration) => addToast(msg, 'error', duration),
    [addToast],
  );

  toast.info = useCallback(
    (msg, duration) => addToast(msg, 'info', duration),
    [addToast],
  );

  toast.warning = useCallback(
    (msg, duration) => addToast(msg, 'warning', duration),
    [addToast],
  );

  toast.dismiss = useCallback((id) => {
    if (id) {
      removeToast(id);
    } else {
      Object.values(exitTimers.current).forEach(clearTimeout);
      exitTimers.current = {};
      setToasts([]);
    }
  }, [removeToast]);

  return (
    <ToastContext.Provider value={{ toasts, toast, removeToast }}>
      <ToastStack />
      {children}
    </ToastContext.Provider>
  );
}
