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

    let avatarContent;

    if (user?.avatar_url && !imgError) {
        avatarContent = (
            <img
                src={user.avatar_url}
                alt={user.name || 'Avatar'}
                className={`${sizeClass} object-cover rounded-full`}
                onError={() => setImgError(true)}
            />
        );
    } else if (fallbackType === 'bayanihan') {
        avatarContent = (
            <img
                src="/images/defaults/bayanihan-logo.png"
                alt="Bayanihan Logo"
                className={`${sizeClass} object-contain rounded-full`}
            />
        );
    } else if (fallbackType === 'agency') {
        if (user?.agency?.logo_url && !imgError) {
            avatarContent = (
                <img
                    src={user.agency.logo_url}
                    alt={`${user.agency?.name || 'Agency'} Logo`}
                    className={`${sizeClass} object-contain rounded-full`}
                    onError={() => setImgError(true)}
                />
            );
        }
    }

    if (!avatarContent) {
        avatarContent = (
            <span
                className={`${sizeClass} inline-flex items-center justify-center rounded-full text-white font-bold ${getAvatarColor(user?.name)}`}
            >
                {getInitials(user?.name)}
            </span>
        );
    }

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                className={`${sizeClass} rounded-full flex-shrink-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                aria-label={`View ${user?.name || 'user'}'s profile`}
            >
                {avatarContent}
            </button>
        );
    }

    return avatarContent;
}

export default UserAvatar;
export { getAvatarColor, getInitials };
