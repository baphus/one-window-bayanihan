import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { cardShell, pageHeadingStyles } from './pageHeadingStyles';

// Shared collapsible section card. Replaces the two duplicated inline
// SectionAccordion definitions that lived in GeographicSection and
// EmploymentSection.
export default function SectionAccordion({ title, defaultOpen = false, right = null, children }) {
  const [open, setOpen] = useState(defaultOpen);

  return (
    <article className={cardShell}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="flex w-full items-center justify-between p-4"
      >
        <h3 className={pageHeadingStyles.sectionTitle}>{title}</h3>
        <span className="flex items-center gap-2">
          {right}
          <ChevronDown
            className={`h-4 w-4 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
          />
        </span>
      </button>
      {open && <div className="px-4 pb-4">{children}</div>}
    </article>
  );
}
