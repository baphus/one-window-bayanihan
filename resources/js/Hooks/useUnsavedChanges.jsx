import { useEffect, useRef, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import UnsavedChangesModal from '@/Components/UnsavedChangesModal';

export default function useUnsavedChanges(dirty, { onDiscard, onSaveDraft } = {}) {
  const [showModal, setShowModal] = useState(false);
  const dirtyRef = useRef(dirty);
  const pendingVisitRef = useRef(null);
  const bypassRef = useRef(false);
  const allowReloadRef = useRef(false);

  dirtyRef.current = dirty;

  // Intercept Inertia SPA navigation
  useEffect(() => {
    const handler = (event) => {
      const visit = event.detail.visit;
      if (!visit) return;

      // Consume the bypass BEFORE the method check. A non-GET visit also fires
      // this event, so returning first for non-GET would leave the flag armed
      // and spend it on the next unrelated GET — navigating a dirty form away
      // with no warning. The flag is one-shot for whichever visit comes next.
      const bypassed = bypassRef.current;
      bypassRef.current = false;
      if (bypassed) {
        if (visit.method !== 'get') {
          allowReloadRef.current = window.location.href.split('?')[0];
        }
        return;
      }

      if (visit.method !== 'get') return;
      if (allowReloadRef.current && visit.url.split('?')[0] === allowReloadRef.current) {
        allowReloadRef.current = false;
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
      // A bypass in flight means a submission/visit is already leaving, so the
      // browser prompt would be noise. Note this READS the flag without
      // consuming it: `beforeunload` can fire more than once for one unload, and
      // consuming it here would re-arm the prompt mid-teardown. The SPA
      // navigation handler above owns consuming the flag.
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
