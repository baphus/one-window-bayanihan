import { useState, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import UserAvatar from '@/Components/ui/UserAvatar';

export default function ProfileHeader() {
    const user = usePage().props.auth.user;
    const roleLabels = { CASE_MANAGER: 'Case Manager', AGENCY: 'Agency Focal', ADMIN: 'System Admin' };
    const [preview, setPreview] = useState(null);
    const [selectedFile, setSelectedFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const fileInputRef = useRef(null);

    const displayUrl = preview || user.avatar_url;
    const hasImage = !!displayUrl;
    const hasChanges = !!selectedFile;

    function handleFileChange(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        setError(null);
        setSelectedFile(file);
        const reader = new FileReader();
        reader.onload = (ev) => setPreview(ev.target.result);
        reader.readAsDataURL(file);
    }

    function handleSave() {
        if (!selectedFile) return;
        setUploading(true);
        setError(null);
        const formData = new FormData();
        formData.append('avatar', selectedFile);
        router.post(route('users.avatar.store', { user: user.id }), formData, {
            preserveScroll: true,
            headers: { 'Content-Type': 'multipart/form-data' },
            onFinish: () => setUploading(false),
            onError: (errors) => {
                setError(errors.avatar || 'Failed to upload avatar.');
                setUploading(false);
            },
        });
    }

    function handleCancel() {
        setPreview(null);
        setSelectedFile(null);
        setError(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    return (
        <div className="flex items-center gap-6">
            <div className="relative group shrink-0">
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="h-20 w-20 rounded-full overflow-hidden flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition disabled:opacity-50"
                    disabled={uploading}
                    aria-label="Change profile picture"
                >
                    {hasImage ? (
                        <img src={displayUrl} alt={user.name} className="h-full w-full object-cover" />
                    ) : (
                        <div className="h-full w-full bg-indigo-100 flex items-center justify-center">
                            <span className="text-2xl font-bold text-indigo-700 select-none">
                                {user.name?.charAt(0)?.toUpperCase() || '?'}
                            </span>
                        </div>
                    )}
                </button>
                {!uploading && (
                    <div
                        className="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <span className="text-white text-[11px] font-semibold">Change</span>
                    </div>
                )}
                <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={handleFileChange} />
            </div>

            <div className="flex-1 min-w-0">
                <h2 className="text-xl font-bold text-slate-900 truncate">{user.name}</h2>
                <p className="text-sm text-slate-500">{roleLabels[user.role] || user.role}</p>
                {user.position && <p className="text-[13px] text-slate-600 mt-0.5">{user.position}</p>}
            </div>

            <div className="shrink-0">
                {hasChanges && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleSave}
                            disabled={uploading}
                            className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition"
                        >
                            {uploading ? 'Saving...' : 'Save'}
                        </button>
                        <button
                            type="button"
                            onClick={handleCancel}
                            disabled={uploading}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition"
                        >
                            Cancel
                        </button>
                    </div>
                )}
            </div>

            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        </div>
    );
}
