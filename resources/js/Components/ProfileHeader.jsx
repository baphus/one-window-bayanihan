import { useState, useRef, useEffect } from 'react';
import { usePage } from '@inertiajs/react';

export default function ProfileHeader({ onAvatarSelect, avatarPreview, saving }) {
    const user = usePage().props.auth.user;
    const roleLabels = { CASE_MANAGER: 'Case Manager', AGENCY: 'Agency Focal', ADMIN: 'System Admin' };
    const fileInputRef = useRef(null);

    const [imgError, setImgError] = useState(false);
    const displayUrl = avatarPreview || user.avatar_url;
    const hasImage = !!displayUrl && !imgError;
    const hasNewFile = !!avatarPreview;

    useEffect(() => {
        setImgError(false);
    }, [displayUrl]);

    function handleFileChange(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        onAvatarSelect?.(file);
    }

    return (
        <div className="flex items-center gap-6">
            <div className="relative group shrink-0">
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className={`h-20 w-20 rounded-circle overflow-hidden flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition disabled:opacity-50 ${hasNewFile ? 'ring-2 ring-indigo-500 ring-offset-2' : ''}`}
                    disabled={saving}
                    aria-label="Change profile picture"
                >
                    {hasImage ? (
                        <img
                            src={displayUrl}
                            alt={user.name}
                            className="h-full w-full object-cover"
                            onError={() => setImgError(true)}
                        />
                    ) : (
                        <div className="h-full w-full bg-indigo-100 flex items-center justify-center relative">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                className="absolute w-3/5 h-3/5 text-indigo-300/50"
                            >
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <span className="relative text-2xl font-bold text-indigo-700 select-none z-10">
                                {user.name?.charAt(0)?.toUpperCase() || '?'}
                            </span>
                        </div>
                    )}

                    {saving && (
                        <div className="absolute inset-0 rounded-circle bg-white/60 flex items-center justify-center">
                            <svg className="animate-spin h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        </div>
                    )}
                </button>

                {!saving && (
                    <div
                        className="absolute inset-0 rounded-circle bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <span className="text-white text-[11px] font-semibold">
                            {hasNewFile ? 'Replace' : 'Change'}
                        </span>
                    </div>
                )}

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={handleFileChange}
                    disabled={saving}
                />
            </div>

            <div className="flex-1 min-w-0">
                <h2 className="text-xl font-bold text-slate-900 truncate">{user.name}</h2>
                <p className="text-sm text-slate-500">{roleLabels[user.role] || user.role}</p>
                {user.position && <p className="text-[13px] text-slate-600 mt-0.5">{user.position}</p>}
            </div>

            {hasNewFile && (
                <div className="shrink-0">
                    <span className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                        New avatar selected
                    </span>
                </div>
            )}
        </div>
    );
}
