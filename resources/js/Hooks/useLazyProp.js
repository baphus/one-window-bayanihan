import { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';

/**
 * Lazily load an Inertia page prop via partial reload.
 *
 * Fires `router.reload({ only: [key] })` on mount if the prop isn't already
 * present in the page's props. Uses `useRef` to guard against double-fetch
 * in React StrictMode (dev double-mount cycle).
 *
 * @param {string} key — The prop key to load (e.g. 'agencyScorecard')
 * @returns {[any, boolean, any]} [data, isLoading, error]
 */
export function useLazyProp(key) {
  const { props } = usePage();
  const hasInitialData = key in props && props[key] !== undefined;

  const [data, setData] = useState(
    hasInitialData ? props[key] : undefined,
  );
  const [isLoading, setIsLoading] = useState(!hasInitialData);
  const [error, setError] = useState(null);
  const fetchedKeyRef = useRef(null);

  useEffect(() => {
    // Prop already available — nothing to fetch
    if (hasInitialData) {
      fetchedKeyRef.current = key;
      setIsLoading(false);
      return;
    }

    // Guard: StrictMode double-mount, don't fire a second reload for the same key
    if (fetchedKeyRef.current === key) return;
    fetchedKeyRef.current = key;

    setIsLoading(true);
    setError(null);

    router.reload({
      only: [key],
      onSuccess: (page) => {
        setData(page.props[key]);
        setIsLoading(false);
      },
      onError: (err) => {
        setError(err);
        setIsLoading(false);
      },
    });
  }, [key, hasInitialData]);

  return [data, isLoading, error];
}
