import { useEffect } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    useEffect(() => {
        if (!show || !closeable) {
            return;
        }
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [show, closeable, onClose]);

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    if (!show) {
        return null;
    }

    return (
        <div
            id="modal"
            role="dialog"
            aria-modal="true"
            className="fixed inset-0 z-50 overflow-y-auto"
        >
            <div className="fixed inset-0 bg-gray-500/75" aria-hidden="true" onClick={close} />

            {/* Scroll lives on the root above; this wrapper only centers.
                min-h-full + m-auto keeps tall panels reachable while centering short ones. */}
            <div
                className="relative flex min-h-full items-center justify-center px-4 py-6"
                onClick={close}
            >
                <div
                    className={`relative m-auto flex max-h-[90vh] w-full transform flex-col overflow-y-auto rounded-lg bg-white shadow-xl transition-all owb-modal-animate ${maxWidthClass}`}
                    onClick={(event) => event.stopPropagation()}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
