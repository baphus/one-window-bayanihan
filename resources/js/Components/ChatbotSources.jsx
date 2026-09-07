import { Link } from '@inertiajs/react';

export default function ChatbotSources({ sources = [] }) {
    const unique = [...new Map((Array.isArray(sources) ? sources : [])
        .filter((source) => source && typeof source.slug === 'string')
        .map((source) => [`${source.source_type}:${source.slug}`, source])).values()];
    if (!unique.length) return null;

    return (
        <div className="border-t border-outline-variant/30 px-4 py-3" aria-label="Answer sources">
            <p className="mb-1 text-xs font-semibold text-on-surface-variant">Sources</p>
            <ul className="space-y-1 text-xs">
                {unique.map((source) => {
                    // Construct the article route from a known slug, never a model URL.
                    const article = source.source_type === 'helpdesk' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(source.slug);
                    return (
                        <li key={`${source.source_type}:${source.slug}`}>
                            {article ? (
                                <Link href={route('helpdesk.show', source.slug)} className="text-primary underline underline-offset-2 focus-visible:outline focus-visible:outline-2">
                                    {source.article_title || source.heading || source.slug}
                                </Link>
                            ) : <span>{source.heading || 'Agency directory'}</span>}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
