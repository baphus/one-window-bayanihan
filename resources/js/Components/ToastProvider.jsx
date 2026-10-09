import { useEffect, useContext, useState, useCallback, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { useToast, ToastContext } from '@/Hooks/useToast.jsx';
import AppToast from '@/Components/ui/AppToast';

let toastId = 0;

const EXIT_ANIMATION_MS = 300;

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
  const timersRef = useRef({});

  const removeToast = useCallback((id) => {
    setToasts((prev) =>
      prev.map((t) => (t.id === id ? { ...t, exiting: true } : t)),
    );
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
      delete timersRef.current[id];
    }, EXIT_ANIMATION_MS);
    clearTimeout(timersRef.current[id]);
    delete timersRef.current[id];
  }, []);

  const addToast = useCallback((message, tone = 'info', duration = 4000) => {
    const id = ++toastId;
    setToasts((prev) => [...prev, { id, message, tone, exiting: false }]);
    timersRef.current[id] = setTimeout(() => removeToast(id), duration);
    return id;
  }, [removeToast]);

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
      setToasts((prev) =>
        prev.map((t) => ({ ...t, exiting: true })),
      );
      setTimeout(() => setToasts([]), EXIT_ANIMATION_MS);
    }
  }, [removeToast]);

  return (
    <ToastContext.Provider value={{ toasts, toast, removeToast }}>
      <ToastStack />
      {children}
    </ToastContext.Provider>
  );
}
