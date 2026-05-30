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

function UserAvatar({ user, size = 'sm', fallbackType = 'initials' }) {
    const sizeMap = { sm: 'h-6 w-6 text-[9px]', md: 'h-8 w-8 text-[11px]', lg: 'h-10 w-10 text-[13px]' };
    const classes = `${sizeMap[size] || sizeMap.sm} rounded-full flex-shrink-0`;
    const [imgError, setImgError] = useState(false);

    if (user?.avatar_url && !imgError) {
        return (
            <img
                src={user.avatar_url}
                alt={user.name || 'Avatar'}
                className={`${classes} object-cover`}
                onError={() => setImgError(true)}
            />
        );
    }

    if (fallbackType === 'bayanihan') {
        return (
            <img
                src="/images/defaults/bayanihan-logo.png"
                alt="Bayanihan Logo"
                className={`${classes} object-contain`}
            />
        );
    }

    if (fallbackType === 'agency') {
        if (user?.agency?.logo_url && !imgError) {
            return (
                <img
                    src={user.agency.logo_url}
                    alt={`${user.agency?.name || 'Agency'} Logo`}
                    className={`${classes} object-contain`}
                    onError={() => setImgError(true)}
                />
            );
        }
    }

    return (
        <span
            className={`${classes} inline-flex items-center justify-center rounded-full text-white font-bold ${getAvatarColor(user?.name)}`}
        >
            {getInitials(user?.name)}
        </span>
    );
}

export default UserAvatar;
export { getAvatarColor, getInitials };
