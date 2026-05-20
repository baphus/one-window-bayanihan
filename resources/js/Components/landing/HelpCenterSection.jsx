import { Link } from '@inertiajs/react';

const features = [
  {
    icon: 'search',
    title: 'Search Articles',
    description: 'Find answers quickly with our global search across all knowledge base articles.',
  },
  {
    icon: 'category',
    title: 'Browse by Category',
    description: 'Navigate organized categories from OFW assistance to system administration guides.',
  },
  {
    icon: 'badge',
    title: 'Role-Specific Guides',
    description: 'Content tailored for OFWs, Case Managers, Agency Partners, and Administrators.',
  },
  {
    icon: 'help',
    title: 'Frequently Asked Questions',
    description: 'Quick answers to the most common questions about the system and services.',
  },
  {
    icon: 'history',
    title: 'Step-by-Step Procedures',
    description: 'Detailed guides with workflows, document checklists, and status reference tables.',
  },
  {
    icon: 'thumb_up',
    title: 'Article Feedback',
    description: 'Let us know if an article was helpful so we can keep improving the content.',
  },
];

export default function HelpCenterSection() {
  return (
    <section id="help-center" className="bg-white px-8 py-24">
      <div className="mx-auto max-w-7xl">
        <div className="mb-4 text-center">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
            <span className="material-symbols-outlined text-sm">help</span>
            Knowledge Base
          </span>
        </div>

        <h2 className="mb-4 text-center font-headline text-3xl font-extrabold text-slate-900 md:text-4xl">
          Help Center
        </h2>
        <p className="mx-auto mb-12 max-w-2xl text-center text-base leading-relaxed text-slate-500">
          A centralized self-service knowledge hub with guides, procedures, and troubleshooting
          information for all system users.
        </p>

        <div className="mb-12 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
          {features.map((f) => (
            <div
              key={f.title}
              className="rounded-lg border border-slate-200 bg-slate-50 p-6 transition-all hover:border-primary/20 hover:shadow-sm"
            >
              <span className="material-symbols-outlined mb-3 inline-block text-2xl text-primary">
                {f.icon}
              </span>
              <h3 className="mb-1 text-sm font-bold text-slate-900">{f.title}</h3>
              <p className="text-xs leading-relaxed text-slate-500">{f.description}</p>
            </div>
          ))}
        </div>

        <div className="text-center">
          <Link
            href={route('helpdesk.index')}
            className="inline-flex items-center gap-2 rounded-md bg-primary px-8 py-3.5 text-sm font-bold uppercase tracking-widest text-white shadow-sm transition-all hover:bg-primary/90 active:scale-95"
          >
            <span className="material-symbols-outlined text-lg">menu_book</span>
            Visit Help Center
          </Link>
        </div>
      </div>
    </section>
  );
}
