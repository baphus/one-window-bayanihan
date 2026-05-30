import { useState, useRef } from 'react';
import { router } from '@inertiajs/react';

const sizeMap = {
    sm: 'h-12 w-12 text-sm',
    md: 'h-16 w-16 text-lg',
    lg: 'h-24 w-24 text-2xl',
};

function getInitial(name) {
    if (!name) return '?';
    return name.charAt(0).toUpperCase();
}

export default function ProfilePictureUpload({
    currentUrl,
    name,
    size = 'lg',
    clientId,
}) {
    const [preview, setPreview] = useState(null);
    const [selectedFile, setSelectedFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const fileInputRef = useRef(null);

    const displayUrl = preview || currentUrl;
    const hasImage = !!displayUrl;
    const hasChanges = !!selectedFile;
    const dimensions = sizeMap[size] || sizeMap.lg;

    function handleFileChange(event) {
        const file = event.target.files?.[0];
        if (!file) return;

        setError(null);
        setSelectedFile(file);

        const reader = new FileReader();
        reader.onload = (e) => setPreview(e.target.result);
        reader.readAsDataURL(file);
    }

    function handleSave() {
        if (!selectedFile || !clientId) return;

        setUploading(true);
        setError(null);

        const formData = new FormData();
        formData.append('profile_picture', selectedFile);

        router.post(route('clients.avatar.store', { client: clientId }), formData, {
            preserveScroll: true,
            headers: { 'Content-Type': 'multipart/form-data' },
            onFinish: () => {
                setUploading(false);
            },
            onError: (errors) => {
                const message = errors.profile_picture || 'Failed to upload profile picture.';
                setError(message);
                setUploading(false);
            },
        });
    }

    function handleCancel() {
        setPreview(null);
        setSelectedFile(null);
        setError(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function handleRemove() {
        if (!clientId) return;

        const confirmed = window.confirm('Are you sure you want to remove this profile picture?');
        if (!confirmed) return;

        setUploading(true);
        setError(null);

        router.delete(route('clients.avatar.destroy', { client: clientId }), {
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
            },
            onError: (errors) => {
                const message = errors.avatar || 'Failed to remove profile picture.';
                setError(message);
                setUploading(false);
            },
        });
    }

    function triggerFileInput() {
        if (uploading) return;
        fileInputRef.current?.click();
    }

    return (
        <div className="flex flex-col items-center gap-3">
            {/* Avatar container */}
            <div className="relative group">
                <button
                    type="button"
                    onClick={triggerFileInput}
                    className={`${dimensions} rounded-full overflow-hidden flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ${
                        hasImage ? 'bg-transparent' : 'bg-indigo-100'
                    }`}
                    disabled={uploading}
                    aria-label="Change profile picture"
                >
                    {hasImage ? (
                        <img
                            src={displayUrl}
                            alt={name || 'Profile picture'}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <span className="font-semibold text-indigo-700 select-none">
                            {getInitial(name)}
                        </span>
                    )}
                </button>

                {/* Hover overlay */}
                {!uploading && (
                    <div
                        className="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
                        onClick={triggerFileInput}
                    >
                        <span className="text-white text-xs font-medium">Change</span>
                    </div>
                )}

                {/* Hidden file input */}
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png"
                    className="hidden"
                    onChange={handleFileChange}
                />
            </div>

            {/* Validation error */}
            {error && (
                <p className="text-sm text-red-600">{error}</p>
            )}

            {/* Save / Cancel buttons (visible when a new file is selected) */}
            {hasChanges && (
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleSave}
                        disabled={uploading}
                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 disabled:opacity-25"
                    >
                        {uploading ? (
                            <>
                                <svg
                                    className="animate-spin -ml-1 mr-2 h-3 w-3 text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                Saving...
                            </>
                        ) : (
                            'Save'
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={handleCancel}
                        disabled={uploading}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                    >
                        Cancel
                    </button>
                </div>
            )}

            {/* Remove button (visible when there is an existing picture and no pending changes) */}
            {currentUrl && !hasChanges && (
                <button
                    type="button"
                    onClick={handleRemove}
                    disabled={uploading}
                    className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-red-600 shadow-sm transition duration-150 ease-in-out hover:bg-red-50 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        className="h-3.5 w-3.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fillRule="evenodd"
                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                            clipRule="evenodd"
                        />
                    </svg>
                    Remove
                </button>
            )}
        </div>
    );
}
