import { useState } from 'react';

const avatarColors = [
    'bg-[#0b5384]', 'bg-[#6b21a8]', 'bg-[#15803d]', 'bg-[#b45309]',
    'bg-[#be123c]', 'bg-[#1d4ed8]', 'bg-[#0d9488]', 'bg-[#a21caf]',
];

function getAvatarColor(name) {
    if (!name) return avatarColors[0];
    const hash = name.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
    return avatarColors[hash % avatarColors.length];
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map((n) => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

function UserAvatar({ user, size = 'sm', fallbackType = 'initials', onClick }) {
    const sizeMap = { sm: 'h-6 w-6 text-[9px]', md: 'h-8 w-8 text-[11px]', lg: 'h-10 w-10 text-[13px]' };
    const sizeClass = sizeMap[size] || sizeMap.sm;
    const [imgError, setImgError] = useState(false);

    const hasImage = user?.avatar_url && !imgError;
    const fallbackImg = fallbackType === 'bayanihan' || fallbackType === 'agency';

    // Inner content: either an <img> or the initials fallback
    let innerContent;

    if (hasImage) {
        innerContent = (
            <img
                src={user.avatar_url}
                alt={user.name || 'Avatar'}
                className="h-full w-full object-cover"
                onError={() => setImgError(true)}
            />
        );
    } else if (fallbackType === 'bayanihan') {
        innerContent = (
            <img
                src="/images/defaults/bayanihan-logo.png"
                alt="Bayanihan Logo"
                className="h-full w-full object-contain"
            />
        );
    } else if (fallbackType === 'agency') {
        if (user?.agency?.logo_url && !imgError) {
            innerContent = (
                <img
                    src={user.agency.logo_url}
                    alt={`${user.agency?.name || 'Agency'} Logo`}
                    className="h-full w-full object-contain"
                    onError={() => setImgError(true)}
                />
            );
        }
    }

    if (!innerContent) {
        innerContent = (
            <>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    className="absolute w-3/5 h-3/5 text-white/20"
                >
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                <span className="relative z-10">
                    {getInitials(user?.name)}
                </span>
            </>
        );
    }

    // Shared container: square + circular clip
    const containerClass = `${sizeClass} rounded-full overflow-hidden flex-shrink-0 inline-flex items-center justify-center relative ${
        hasImage || fallbackImg ? '' : getAvatarColor(user?.name) + ' text-white font-bold'
    }`;

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                className={`${containerClass} cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                aria-label={`View ${user?.name || 'user'}'s profile`}
            >
                {innerContent}
            </button>
        );
    }

    return (
        <span className={containerClass}>
            {innerContent}
        </span>
    );
}

export default UserAvatar;
export { getAvatarColor, getInitials };
