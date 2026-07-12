import { useCallback, useEffect, useRef, useState } from 'react';

export default function useTableVisitLoading() {
  const [isLoading, setIsLoading] = useState(false);
  const mountedRef = useRef(true);

  useEffect(() => {
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const safelySetLoading = useCallback((value) => {
    if (mountedRef.current) {
      setIsLoading(value);
    }
  }, []);

  const withLoading = useCallback((options = {}) => ({
    ...options,
    showProgress: options.showProgress ?? false,
    onStart: (visit) => {
      safelySetLoading(true);
      options.onStart?.(visit);
    },
    onFinish: (visit) => {
      safelySetLoading(false);
      options.onFinish?.(visit);
    },
    onCancel: (visit) => {
      safelySetLoading(false);
      options.onCancel?.(visit);
    },
  }), [safelySetLoading]);

  return { isLoading, setIsLoading: safelySetLoading, withLoading };
}
