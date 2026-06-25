import { useEffect, useRef, useState, useCallback } from 'react';
import { router } from '@inertiajs/react';

export default function useUnsavedChanges(dirty) {
  const [showModal, setShowModal] = useState(false);
  const dirtyRef = useRef(dirty);
  const pendingVisitRef = useRef(null);
  const bypassRef = useRef(false);

  dirtyRef.current = dirty;

  useEffect(() => {
    const handler = (event) => {
      if (bypassRef.current) {
        bypassRef.current = false;
        return;
      }
      const visit = event.detail.visit;
      if (!visit || visit.method !== 'GET') return;
      if (dirtyRef.current) {
        pendingVisitRef.current = visit;
        setShowModal(true);
        return false;
      }
    };

    const remove = router.on('before', handler);
    return () => {
      if (typeof remove === 'function') remove();
    };
  }, []);

  useEffect(() => {
    if (!dirty) return;
    const handler = (e) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  const confirmNavigation = useCallback(() => {
    bypassRef.current = true;
    setShowModal(false);
    const visit = pendingVisitRef.current;
    pendingVisitRef.current = null;
    if (visit) {
      router.visit(visit.url, {
        method: visit.method,
        data: visit.data,
        replace: visit.replace,
        preserveScroll: true,
      });
    }
  }, []);

  const cancelNavigation = useCallback(() => {
    setShowModal(false);
    pendingVisitRef.current = null;
  }, []);

  const bypassNext = useCallback(() => {
    bypassRef.current = true;
  }, []);

  return { showModal, confirmNavigation, cancelNavigation, bypassNext };
}
