import { useEffect, useLayoutEffect, useRef, useState } from 'react';

/**
 * Context menu for a table row's kebab action.
 *
 * Opens at the pointer, or anchored under its trigger button when the caller
 * passes `anchorRect` (keyboard activation). Position is clamped to the
 * viewport so a row near the right or bottom edge cannot push the menu
 * off-screen, and the menu carries real menu semantics: `role="menu"`, arrow
 * key roving focus, Escape to close, and focus restored to the trigger.
 */
export function RowContextMenu({ x, y, anchorRect, triggerRef, onClose, children, labelledBy, label = 'Row actions' }) {
  const ref = useRef(null);
  const focusTargetRef = useRef({ captured: false, element: null });
  const focusRestoredRef = useRef(false);
  const [position, setPosition] = useState({ left: x, top: y, ready: false });

  useLayoutEffect(() => {
    if (focusTargetRef.current.captured) return;

    const explicitTrigger = triggerRef?.current;
    const candidate = explicitTrigger ?? (anchorRect ? document.activeElement : null);

    focusTargetRef.current = {
      captured: true,
      element: candidate instanceof HTMLElement && candidate !== document.body && candidate.isConnected
        ? candidate
        : null,
    };
  }, [anchorRect, triggerRef]);

  function restoreFocus() {
    if (focusRestoredRef.current) return;
    focusRestoredRef.current = true;

    const element = focusTargetRef.current.element;
    if (element instanceof HTMLElement && element.isConnected) {
      element.focus({ preventScroll: true });
    }
  }

  function requestClose() {
    restoreFocus();
    onClose();
  }

  useLayoutEffect(() => {
    const node = ref.current;
    if (!node) return;

    const menuRect = node.getBoundingClientRect();
    const margin = 8;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    let left = x;
    let top = y;

    if (anchorRect) {
      left = anchorRect.right - menuRect.width;
      top = anchorRect.bottom + 4;
    }

    left = Math.min(Math.max(margin, left), Math.max(margin, viewportWidth - menuRect.width - margin));
    top = Math.min(Math.max(margin, top), Math.max(margin, viewportHeight - menuRect.height - margin));

    setPosition({ left, top, ready: true });
  }, [anchorRect, x, y]);

  useEffect(() => {
    const timer = setTimeout(() => {
      document.addEventListener('mousedown', handleMouseDown);
      document.addEventListener('keydown', handleKeyDown);
    }, 0);

    const menuNode = ref.current;
    const firstItem = menuNode?.querySelector('[role="menuitem"]');
    if (focusTargetRef.current.element) {
      firstItem?.focus();
    }

    return () => {
      clearTimeout(timer);
      document.removeEventListener('mousedown', handleMouseDown);
      document.removeEventListener('keydown', handleKeyDown);
      restoreFocus();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function handleMouseDown(e) {
    if (ref.current && !ref.current.contains(e.target)) requestClose();
  }

  function handleKeyDown(e) {
    if (e.key === 'Escape') {
      e.stopPropagation();
      requestClose();
      return;
    }

    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Home' && e.key !== 'End') {
      return;
    }

    const items = Array.from(ref.current?.querySelectorAll('[role="menuitem"]') ?? []);
    if (items.length === 0) return;

    e.preventDefault();
    const currentIndex = items.indexOf(document.activeElement);
    let nextIndex;

    if (e.key === 'Home') nextIndex = 0;
    else if (e.key === 'End') nextIndex = items.length - 1;
    else if (e.key === 'ArrowDown') nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % items.length;
    else nextIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;

    items[nextIndex]?.focus();
  }

  return (
    <div className="fixed inset-0 z-50" onContextMenu={(e) => e.preventDefault()}>
      <div className="absolute inset-0" onMouseDown={requestClose} />
      <div
        ref={ref}
        role="menu"
        aria-label={labelledBy ? undefined : label}
        aria-labelledby={labelledBy}
        className="absolute min-w-[176px] rounded-md border border-slate-200 bg-white py-1 shadow-lg"
        style={{
          left: position.left,
          top: position.top,
          visibility: position.ready ? 'visible' : 'hidden',
        }}
      >
        {children}
      </div>
    </div>
  );
}

export function RowContextMenuItem({ icon, label, onClick, variant = 'default' }) {
  const colorClasses = variant === 'danger'
    ? 'text-red-600 hover:bg-red-50 focus-visible:bg-red-50'
    : variant === 'success'
      ? 'text-emerald-700 hover:bg-emerald-50 focus-visible:bg-emerald-50'
      : 'text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50';

  return (
    <button
      type="button"
      role="menuitem"
      onClick={onClick}
      className={`flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-[13px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${colorClasses}`}
    >
      {icon && <span aria-hidden="true" className="material-symbols-outlined text-[18px] text-slate-400">{icon}</span>}
      {label}
    </button>
  );
}
