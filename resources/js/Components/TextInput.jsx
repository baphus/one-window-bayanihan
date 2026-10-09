import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'w-full rounded-md border-outline-variant bg-surface-bright text-sm text-on-surface shadow-sm placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-2 focus:ring-primary/40 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 ' +
                className
            }
            ref={localRef}
        />
    );
});
