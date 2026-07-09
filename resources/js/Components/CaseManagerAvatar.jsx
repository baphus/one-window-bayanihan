import { useState } from 'react';

const sizeMap = {
    sm: 'h-6 w-6 text-[10px]',
    md: 'h-8 w-8 text-xs',
    lg: 'h-10 w-10 text-sm',
};

const imageClasses = 'rounded-full object-cover border border-slate-200';

const initialsClasses = 'rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-semibold';

const BAYANIHAN_LOGO = '/images/bayanihan-logo.svg';

function getInitials(name) {
    if (!name) return '?';
    return name.charAt(0).toUpperCase();
}

/**
 * CaseManagerAvatar
 *
 * 3-level fallback: user avatar → Bayanihan logo → initials
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
                    className="h-full w-full rounded-full object-cover border border-slate-200"
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
                    className="h-full w-full rounded-full object-contain border border-slate-200"
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

    // Level 2: initials fallback
    return (
        <span className={`group relative inline-block ${sizeClass}`}>
            <span className={`h-full w-full ${initialsClasses}`}>
                {getInitials(user?.name)}
            </span>
            {user?.name && (
                <span className="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[10px] text-white opacity-0 shadow-sm transition-opacity group-hover:opacity-100">
                    {user.name}
                </span>
            )}
        </span>
    );
}
