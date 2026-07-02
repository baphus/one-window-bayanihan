import { useState, useRef, useEffect } from 'react';

const ACCEPTED_TYPES = ['image/png', 'image/jpeg', 'image/svg+xml'];
const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2MB

export default function LogoUpload({ currentLogoUrl, onChange }) {
    const [previewUrl, setPreviewUrl] = useState(null);
    const [error, setError] = useState(null);
    const fileInputRef = useRef(null);

    const hasSelection = !!previewUrl;
    const showPreview = previewUrl || currentLogoUrl;

    // Revoke object URLs on unmount or when previewUrl changes
    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function handleFileChange(event) {
        const file = event.target.files?.[0];
        if (!file) return;

        setError(null);

        // Validate type
        if (!ACCEPTED_TYPES.includes(file.type)) {
            setError('Only PNG, JPEG, and SVG files are accepted.');
            event.target.value = '';
            return;
        }

        // Validate size
        if (file.size > MAX_SIZE_BYTES) {
            setError('File must be less than 2MB.');
            event.target.value = '';
            return;
        }

        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        onChange(file);
    }

    function handleRemove() {
        // Revoke previous preview URL
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        setPreviewUrl(null);
        setError(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        onChange(null);
    }

    return (
        <div>
            <label className="block text-sm font-medium text-slate-700">Logo</label>

            <div className="mt-1 border-2 border-dashed border-gray-300 rounded-lg p-4">
                {/* Preview */}
                {showPreview ? (
                    <div className="flex flex-col items-center gap-2">
                        <img
                            src={previewUrl || currentLogoUrl}
                            alt="Logo preview"
                            className="max-h-32 rounded shadow"
                        />
                        <button
                            type="button"
                            onClick={handleRemove}
                            className="text-sm text-red-500 hover:text-red-700 cursor-pointer"
                        >
                            Remove
                        </button>
                    </div>
                ) : (
                    <p className="text-sm text-slate-500 text-center">No logo selected</p>
                )}

                {/* File input */}
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png,image/jpeg,image/svg+xml"
                    onChange={handleFileChange}
                    className="mt-3 block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-900 file:text-white hover:file:bg-blue-800 file:cursor-pointer"
                />
            </div>

            {/* Error message */}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
