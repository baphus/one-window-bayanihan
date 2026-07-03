import { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

/**
 * Lazily observe an Inertia deferred page prop.
 *
 * Inertia v2 automatically fetches `Inertia::defer()` props after the initial
 * page render — they become available in `usePage().props` as they arrive.
 * This hook surfaces the data with a loading flag so components can show
 * skeletal placeholders while waiting.
 *
 * @param {string} key — The prop key to observe (e.g. 'agencyScorecard')
 * @returns {[any, boolean, any]} [data, isLoading, error]
 */
export function useLazyProp(key) {
  const { props } = usePage();
  const value = props[key];
  const hasData = value !== undefined;

  const [isLoading, setIsLoading] = useState(!hasData);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (hasData) {
      setIsLoading(false);
    }
  }, [hasData]);

  return [value, isLoading, error];
}
