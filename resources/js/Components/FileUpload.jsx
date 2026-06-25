import React, { useRef, useState, useCallback } from 'react';

export default function FileUpload({
    accept,
    maxSize,
    multiple = false,
    onFilesSelected,
    onError,
    label = 'Choose files',
    disabled = false,
    className = '',
}) {
    const inputRef = useRef(null);
    const [isDragOver, setIsDragOver] = useState(false);
    const [error, setError] = useState(null);

    const validateFiles = useCallback((files) => {
        const acceptItems = accept
            ? accept.split(',').map((m) => m.trim()).filter(Boolean)
            : [];

        for (const file of files) {
            // Check file type against accept list
            if (acceptItems.length > 0) {
                const isAccepted = acceptItems.some((item) => {
                    // MIME type check (e.g., "application/pdf")
                    if (item.includes('/')) {
                        if (item.endsWith('/*')) {
                            const typePrefix = item.replace('/*', '/');
                            return file.type.startsWith(typePrefix);
                        }
                        return file.type === item;
                    }
                    // Extension check (e.g., ".pdf")
                    if (item.startsWith('.')) {
                        const ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
                        return ext === item.toLowerCase();
                    }
                    return false;
                });

                if (!isAccepted) {
                    const msg = `File type "${file.name}" is not accepted. Accepted types: ${accept}`;
                    setError(msg);
                    if (onError) onError(msg);
                    return false;
                }
            }

            // Check file size
            if (maxSize && file.size > maxSize) {
                const sizeLabel =
                    maxSize >= 1024 * 1024
                        ? `${Math.round(maxSize / (1024 * 1024))} MB`
                        : `${Math.round(maxSize / 1024)} KB`;
                const msg = `File "${file.name}" exceeds the maximum size of ${sizeLabel}.`;
                setError(msg);
                if (onError) onError(msg);
                return false;
            }
        }

        setError(null);
        return true;
    }, [accept, maxSize, onError]);

    const handleFileChange = useCallback(
        (e) => {
            const files = Array.from(e.target.files || []);
            if (files.length === 0) return;

            if (validateFiles(files)) {
                onFilesSelected(multiple ? files : files[0]);
            }

            // Reset input so same file can be re-selected
            if (inputRef.current) inputRef.current.value = '';
        },
        [multiple, onFilesSelected, validateFiles],
    );

    const handleDragOver = useCallback(
        (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (!disabled) setIsDragOver(true);
        },
        [disabled],
    );

    const handleDragLeave = useCallback(
        (e) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragOver(false);
        },
        [],
    );

    const handleDrop = useCallback(
        (e) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragOver(false);

            if (disabled) return;

            const files = Array.from(e.dataTransfer.files || []);
            if (files.length === 0) return;

            if (validateFiles(files)) {
                onFilesSelected(multiple ? files : files[0]);
            }
        },
        [disabled, multiple, onFilesSelected, validateFiles],
    );

    const handleClick = useCallback(() => {
        if (!disabled && inputRef.current) {
            inputRef.current.click();
        }
    }, [disabled]);

    const handleKeyDown = useCallback(
        (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleClick();
            }
        },
        [handleClick],
    );

    return (
        <div className={`w-full ${className}`}>
            <div
                className={`
                    border-2 border-dashed rounded-[3px] p-6 text-center cursor-pointer
                    transition-colors duration-150
                    ${
                        isDragOver
                            ? 'border-indigo-400 bg-indigo-50'
                            : 'border-slate-300 hover:border-indigo-400'
                    }
                    ${disabled ? 'opacity-50 cursor-not-allowed' : ''}
                `.replace(/\s+/g, ' ').trim()}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                onClick={handleClick}
                onKeyDown={handleKeyDown}
                role="button"
                tabIndex={disabled ? -1 : 0}
                aria-label="File upload drop zone"
                aria-disabled={disabled}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    onChange={handleFileChange}
                    className="hidden"
                    disabled={disabled}
                    aria-hidden="true"
                />

                <div className="flex flex-col items-center gap-2">
                    <svg
                        className="w-8 h-8 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                        />
                    </svg>
                    <p className="text-[12px] text-slate-600">
                        {isDragOver ? 'Drop files here...' : label}
                    </p>
                    <p className="text-[10px] text-slate-400">
                        Drag & drop or click to browse
                    </p>
                </div>
            </div>

            {error && (
                <p className="mt-1 text-rose-600 text-[12px]" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
