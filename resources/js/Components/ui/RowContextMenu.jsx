import { useEffect, useRef } from 'react';

export function RowContextMenu({ x, y, onClose, children }) {
  const ref = useRef(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      document.addEventListener('mousedown', handleMouseDown);
      document.addEventListener('keydown', handleKeyDown);
    }, 0);
    return () => {
      clearTimeout(timer);
      document.removeEventListener('mousedown', handleMouseDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, []);

  function handleMouseDown(e) {
    if (ref.current && !ref.current.contains(e.target)) onClose();
  }

  function handleKeyDown(e) {
    if (e.key === 'Escape') onClose();
  }

  return (
    <div className="fixed inset-0 z-50" onContextMenu={(e) => e.preventDefault()}>
      <div className="absolute inset-0" onMouseDown={onClose} />
      <div
        ref={ref}
        className="absolute bg-white border border-slate-200 rounded-md shadow-lg py-1 min-w-[170px]"
        style={{ left: x, top: y }}
      >
        {children}
      </div>
    </div>
  );
}

export function RowContextMenuItem({ icon, label, onClick, variant = 'default' }) {
  const colorClasses = variant === 'danger'
    ? 'text-red-600 hover:bg-red-50'
    : 'text-slate-700 hover:bg-slate-50';

  return (
    <button
      onClick={onClick}
      className={`w-full flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium transition-colors text-left ${colorClasses}`}
    >
      {icon && <span className="material-symbols-outlined text-[18px] text-slate-400">{icon}</span>}
      {label}
    </button>
  );
}
