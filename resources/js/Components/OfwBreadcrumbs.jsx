import { Link } from '@inertiajs/react';

export default function OfwBreadcrumbs({ items }) {
    return (
        <nav aria-label="Breadcrumb" className="text-[12px] text-slate-500">
            <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                {items.map((item, index) => (
                    <li key={item.label} className="flex min-w-0 items-center gap-1.5">
                        {index > 0 && <span aria-hidden="true">/</span>}
                        {index === items.length - 1 ? (
                            <span aria-current="page" className="font-semibold text-slate-700">{item.label}</span>
                        ) : (
                            <Link href={item.href} className="rounded-sm transition-colors hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                                {item.label}
                            </Link>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
