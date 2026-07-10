import { useState } from 'react';

const sizeMap = {
    sm: 'h-6 w-6 text-[10px]',
    md: 'h-8 w-8 text-xs',
    lg: 'h-10 w-10 text-sm',
};

const imageClasses = 'rounded-circle object-cover border border-slate-200';

const BAYANIHAN_LOGO = '/images/bayanihan-logo.svg';

/**
 * CaseManagerAvatar
 *
 * 3-level fallback: user avatar → Bayanihan logo → person icon
 *
 * @param {object|null} user - { name, avatar_url? }
 * @param {'sm'|'md'|'lg'} size
 */
export default function CaseManagerAvatar({ user, size = 'md' }) {
    const [fallbackLevel, setFallbackLevel] = useState(0);

    const sizeClass = sizeMap[size] || sizeMap.md;

    // Level 0: try user avatar_url
    if (user?.avatar_url && fallbackLevel === 0) {
        return (
            <span className={`group relative inline-block ${sizeClass}`}>
                <img
                    src={user.avatar_url}
                    alt={user.name || 'Avatar'}
                    className="h-full w-full rounded-circle object-cover border border-slate-200"
                    onError={() => setFallbackLevel(1)}
                />
                {user?.name && (
                    <span className="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                        {user.name}
                    </span>
                )}
            </span>
        );
    }

    // Level 1: try Bayanihan logo
    if (fallbackLevel <= 1) {
        return (
            <span className={`group relative inline-block ${sizeClass}`}>
                <img
                    src={BAYANIHAN_LOGO}
                    alt="Bayanihan Logo"
                    className="h-full w-full rounded-circle object-contain border border-slate-200"
                    onError={() => setFallbackLevel(2)}
                />
                {user?.name && (
                    <span className="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                        {user.name}
                    </span>
                )}
            </span>
        );
    }

    // Level 2: person icon fallback
    return (
        <span className={`group relative inline-block ${sizeClass}`}>
            <span className="h-full w-full rounded-circle bg-indigo-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="w-3/5 h-3/5 text-indigo-400/50">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </span>
            {user?.name && (
                <span className="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                    {user.name}
                </span>
            )}
        </span>
    );
}
