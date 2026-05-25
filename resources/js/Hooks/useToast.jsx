import { createContext, useContext, useState, useCallback, useRef } from 'react';

const ToastContext = createContext(null);

let toastId = 0;

export function ToastProviderInner({ children }) {
  const [toasts, setToasts] = useState([]);
  const timersRef = useRef({});

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
    clearTimeout(timersRef.current[id]);
    delete timersRef.current[id];
  }, []);

  const addToast = useCallback((message, tone = 'info', duration = 4000) => {
    const id = ++toastId;
    setToasts((prev) => [...prev, { id, message, tone }]);
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
      setToasts([]);
    }
  }, [removeToast]);

  return (
    <ToastContext.Provider value={{ toasts, toast, removeToast }}>
      {children}
    </ToastContext.Provider>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return ctx.toast;
}

export { ToastContext };
