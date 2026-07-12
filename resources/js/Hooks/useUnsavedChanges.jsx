import { useEffect, useRef, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function useUnsavedChanges(dirty, { onDiscard, onSaveDraft } = {}) {
  const [showModal, setShowModal] = useState(false);
  const dirtyRef = useRef(dirty);
  const pendingVisitRef = useRef(null);
  const bypassRef = useRef(false);

  dirtyRef.current = dirty;

  // Intercept Inertia SPA navigation
  useEffect(() => {
    const handler = (event) => {
      const visit = event.detail.visit;
      if (!visit || visit.method !== 'get') return;
      if (bypassRef.current) {
        bypassRef.current = false;
        return;
      }
      if (dirtyRef.current) {
        event.preventDefault();
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

  // Intercept browser tab close / hard reload
  useEffect(() => {
    if (!dirty) return;
    const handler = (e) => {
      // Skip when a form submission or SPA visit is already in progress
      if (bypassRef.current) return;
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  const confirmNavigation = useCallback(() => {
    const visit = pendingVisitRef.current;
    if (!visit) return; // Guard against double-click
    bypassRef.current = true;
    setShowModal(false);
    pendingVisitRef.current = null;
    onDiscard?.();
    router.visit(visit.url, {
      method: visit.method,
      data: visit.data,
      replace: visit.replace,
    });
  }, [onDiscard]);

  const cancelNavigation = useCallback(() => {
    setShowModal(false);
    pendingVisitRef.current = null;
  }, []);

  const bypassNext = useCallback(() => {
    bypassRef.current = true;
  }, []);

  // Portal-rendered modal — pages just render {UnsavedModal} without importing the component
  const UnsavedModal = showModal
    ? createPortal(
        <UnsavedChangesModal
          show={showModal}
          onConfirm={confirmNavigation}
          onCancel={cancelNavigation}
          onSaveDraft={onSaveDraft}
        />,
        document.body,
      )
    : null;

  return { showModal, confirmNavigation, cancelNavigation, bypassNext, UnsavedModal };
}
