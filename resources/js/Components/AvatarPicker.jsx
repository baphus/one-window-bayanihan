import { useState, useRef, useEffect } from 'react';
import CropImageModal from '@/Components/CropImageModal';

function getInitial(name) {
    if (!name) return '?';
    return name.charAt(0).toUpperCase();
}

/**
 * AvatarPicker
 *
 * Crop-only profile picture picker for forms that own their own submit
 * (registration). Unlike ProfilePictureUpload it never uploads — it hands the
 * cropped File back to the parent, which attaches it to its FormData payload.
 *
 * @param {Object} props
 * @param {File|null} props.file - Currently selected (already cropped) file
 * @param {(file: File|null) => void} props.onFileChange - Called with the cropped File, or null when cleared
 * @param {string} [props.name] - Name used for the initials fallback
 * @param {string} [props.error] - Validation error to display
 * @param {boolean} [props.disabled]
 * @param {string} [props.label] - Heading above the control
 */
export default function AvatarPicker({
    file,
    onFileChange,
    name = '',
    error = null,
    disabled = false,
    label = 'Profile photo',
}) {
    const [preview, setPreview] = useState(null);
    const [cropFile, setCropFile] = useState(null);
    const fileInputRef = useRef(null);

    // Revoke the object URL when the preview is replaced or unmounted.
    useEffect(() => {
        return () => {
            if (preview) URL.revokeObjectURL(preview);
        };
    }, [preview]);

    function handleFileChange(event) {
        const selected = event.target.files?.[0];
        if (!selected) return;
        setCropFile(selected);
    }

    function handleCropComplete(croppedFile) {
        if (preview) URL.revokeObjectURL(preview);
        setPreview(URL.createObjectURL(croppedFile));
        setCropFile(null);
        onFileChange?.(croppedFile);
    }

    function handleCancelCrop() {
        setCropFile(null);
        // Reset so re-picking the same file still fires `change`.
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    }

    function handleClear() {
        if (preview) URL.revokeObjectURL(preview);
        setPreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        onFileChange?.(null);
    }

    function triggerFileInput() {
        if (disabled) return;
        fileInputRef.current?.click();
    }

    return (
        <div>
            {label && (
                <span className="mb-2 block text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                    {label}{' '}
                    <span className="font-medium normal-case tracking-normal text-slate-400">(optional)</span>
                </span>
            )}

            <div className="flex items-center gap-4">
                <div className="relative group shrink-0">
                    <button
                        type="button"
                        onClick={triggerFileInput}
                        disabled={disabled}
                        aria-label="Choose profile photo"
                        className="h-20 w-20 rounded-full overflow-hidden flex items-center justify-center bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition disabled:opacity-50"
                    >
                        {preview ? (
                            <img src={preview} alt="Selected profile photo" className="h-full w-full object-cover" />
                        ) : (
                            <div className="relative h-full w-full flex items-center justify-center">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    className="absolute w-3/5 h-3/5 text-indigo-300/50"
                                    aria-hidden="true"
                                >
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                                </svg>
                                <span className="relative text-2xl font-bold text-indigo-700 select-none z-10">
                                    {getInitial(name)}
                                </span>
                            </div>
                        )}
                    </button>

                    {!disabled && (
                        <div
                            className="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer"
                            onClick={triggerFileInput}
                            aria-hidden="true"
                        >
                            <span className="text-white text-[11px] font-semibold">
                                {preview ? 'Replace' : 'Upload'}
                            </span>
                        </div>
                    )}

                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={handleFileChange}
                        disabled={disabled}
                    />
                </div>

                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-slate-700">
                        {preview ? 'Photo selected' : 'Add a profile photo'}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-500">
                        JPEG, PNG, or WebP. You can add or change this later on your profile page.
                    </p>
                    <div className="mt-2 flex items-center gap-3">
                        <button
                            type="button"
                            onClick={triggerFileInput}
                            disabled={disabled}
                            className="text-xs font-bold text-primary hover:underline disabled:opacity-50"
                        >
                            {preview ? 'Choose a different photo' : 'Choose photo'}
                        </button>
                        {preview && (
                            <button
                                type="button"
                                onClick={handleClear}
                                disabled={disabled}
                                className="text-xs font-bold text-red-600 hover:underline disabled:opacity-50"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {error && <p className="mt-2 text-xs font-semibold text-error">{error}</p>}

            <CropImageModal
                file={cropFile}
                open={cropFile !== null}
                cropShape="round"
                aspect={1}
                outputSize={300}
                title="Crop profile picture"
                confirmLabel="Set as photo"
                onCropComplete={handleCropComplete}
                onCancel={handleCancelCrop}
            />
        </div>
    );
}
