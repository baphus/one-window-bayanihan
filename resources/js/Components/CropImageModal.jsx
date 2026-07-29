import { useState, useCallback, useEffect } from 'react';
import Cropper from 'react-easy-crop';
import 'react-easy-crop/react-easy-crop.css';

/**
 * Generate a cropped image Blob from the crop area pixels.
 * Uses canvas to draw the cropped region, then converts to a JPEG Blob.
 *
 * @param {string} imageSrc - Source URL of the image to crop
 * @param {{ x: number, y: number, width: number, height: number }} pixelCrop
 * @param {number | { width: number, height: number }} outputSize - Output dimensions
 * @returns {Promise<Blob>}
 */
async function getCroppedImg(imageSrc, pixelCrop, outputSize = 300) {
    const image = await new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = imageSrc;
    });
    const canvas = document.createElement('canvas');

    let width, height;
    if (typeof outputSize === 'number') {
        width = height = outputSize;
    } else {
        width = outputSize.width;
        height = outputSize.height;
    }

    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    ctx.drawImage(
        image,
        pixelCrop.x,
        pixelCrop.y,
        pixelCrop.width,
        pixelCrop.height,
        0,
        0,
        width,
        height,
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Canvas toBlob failed'));
                    return;
                }
                resolve(blob);
            },
            'image/jpeg',
            0.9,
        );
    });
}

/**
 * CropImageModal
 *
 * A generic modal that lets the user crop a selected image before use.
 * Supports round (avatar) and rectangular (logo/banner) crops with
 * configurable aspect ratio and output size.
 *
 * @param {Object} props
 * @param {File|null} props.file - The selected image file
 * @param {boolean} props.open - Whether the modal is visible
 * @param {(croppedFile: File) => void} props.onCropComplete - Called with the final cropped File
 * @param {() => void} props.onCancel - Called when user dismisses without cropping
 * @param {'round'|'rect'} [props.cropShape='round'] - Shape of the crop overlay
 * @param {number} [props.aspect=1] - Aspect ratio of the crop window (undefined = free-form)
 * @param {number|{width:number,height:number}} [props.outputSize=300] - Output image dimensions
 * @param {string} [props.title='Crop your photo'] - Modal title
 * @param {string} [props.confirmLabel='Set as profile photo'] - Confirm button label
 */
export default function CropImageModal({
    file,
    open,
    onCropComplete,
    onCancel,
    cropShape = 'round',
    aspect = 1,
    outputSize = 300,
    title = 'Crop your photo',
    confirmLabel = 'Set as profile photo',
}) {
    const [imageSrc, setImageSrc] = useState(null);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [minZoom, setMinZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState(null);
    const [isCropping, setIsCropping] = useState(false);

    // Reset state when a new file comes in or modal opens
    useEffect(() => {
        if (open && file) {
            const url = URL.createObjectURL(file);
            setImageSrc(url);
            setCrop({ x: 0, y: 0 });
            setZoom(1);
            setCroppedAreaPixels(null);
            setIsCropping(false);

            return () => URL.revokeObjectURL(url);
        }
        if (!open) {
            setImageSrc(null);
        }
    }, [file, open]);

    const handleCropAreaComplete = useCallback((_croppedArea, croppedAreaPixels) => {
        setCroppedAreaPixels(croppedAreaPixels);
    }, []);

    const handleZoomChange = useCallback((e) => {
        setZoom(Number(e.target.value));
    }, []);

    const handleConfirm = useCallback(async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        setIsCropping(true);
        try {
            const blob = await getCroppedImg(imageSrc, croppedAreaPixels, outputSize);

            const originalName = file?.name || 'image.jpg';
            const baseName = originalName.replace(/\.[^.]+$/, '');
            const croppedFile = new File([blob], `${baseName}.jpg`, {
                type: 'image/jpeg',
            });

            onCropComplete(croppedFile);
        } catch (err) {
            console.error('Failed to crop image:', err);
        } finally {
            setIsCropping(false);
        }
    }, [imageSrc, croppedAreaPixels, file, onCropComplete, outputSize]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="crop-modal-title"
        >
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
                onClick={onCancel}
            />

            {/* Modal card */}
            <div className="relative z-10 w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl owb-modal-animate overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                    <h2
                        id="crop-modal-title"
                        className="text-base font-semibold text-slate-900 font-headline"
                    >
                        {title}
                    </h2>
                    <button
                        type="button"
                        onClick={onCancel}
                        className="text-slate-400 hover:text-slate-600 transition-colors"
                        aria-label="Close"
                    >
                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>

                {/* Crop area */}
                <div className="relative w-full" style={{ height: '320px', backgroundColor: '#1a1c20' }}>
                    {imageSrc && (
                        <Cropper
                            image={imageSrc}
                            crop={crop}
                            zoom={zoom}
                            aspect={aspect}
                            cropShape={cropShape}
                            showGrid={false}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={handleCropAreaComplete}
                            onMediaLoaded={(mediaSize) => {
                                // Determine the crop window size to compute min zoom
                                const cropSize = typeof outputSize === 'number' ? outputSize : Math.max(outputSize.width, outputSize.height);
                                const min = Math.min(
                                    cropSize / mediaSize.naturalWidth,
                                    cropSize / mediaSize.naturalHeight,
                                );
                                setMinZoom(min);
                                setZoom(min);
                            }}
                            zoomWithScroll={false}
                        />
                    )}
                    {isCropping && (
                        <div className="absolute inset-0 bg-black/40 flex items-center justify-center z-20">
                            <div className="flex flex-col items-center gap-2">
                                <svg
                                    className="animate-spin h-8 w-8 text-white"
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
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                                <span className="text-sm font-medium text-white">
                                    Processing...
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                {/* Zoom slider */}
                <div className="px-6 py-4 border-t border-slate-100">
                    <div className="flex items-center gap-3">
                        <span className="text-xs font-medium text-slate-500 shrink-0">
                            Zoom
                        </span>
                        <div className="flex-1 relative">
                            <input
                                type="range"
                                min={minZoom}
                                max={3}
                                step={0.01}
                                value={zoom}
                                onChange={handleZoomChange}
                                className="w-full h-1.5 bg-slate-200 rounded-full appearance-none cursor-pointer
                                    [&::-webkit-slider-thumb]:appearance-none
                                    [&::-webkit-slider-thumb]:w-4
                                    [&::-webkit-slider-thumb]:h-4
                                    [&::-webkit-slider-thumb]:rounded-full
                                    [&::-webkit-slider-thumb]:bg-indigo-600
                                    [&::-webkit-slider-thumb]:shadow-md
                                    [&::-webkit-slider-thumb]:cursor-pointer
                                    [&::-moz-range-thumb]:w-4
                                    [&::-moz-range-thumb]:h-4
                                    [&::-moz-range-thumb]:rounded-full
                                    [&::-moz-range-thumb]:bg-indigo-600
                                    [&::-moz-range-thumb]:border-0
                                    [&::-moz-range-thumb]:shadow-md
                                    [&::-moz-range-thumb]:cursor-pointer"
                            />
                        </div>
                        <span className="text-xs font-medium text-slate-400 tabular-nums w-10 text-right shrink-0">
                            {zoom.toFixed(1)}x
                        </span>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50">
                    <button
                        type="button"
                        onClick={onCancel}
                        disabled={isCropping}
                        className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50 disabled:opacity-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={isCropping}
                        className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors inline-flex items-center gap-2"
                    >
                        {isCropping ? (
                            <>
                                <svg
                                    className="animate-spin h-4 w-4"
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
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    />
                                </svg>
                                Processing...
                            </>
                        ) : (
                            confirmLabel
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
