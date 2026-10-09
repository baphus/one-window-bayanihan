export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center rounded-md border border-transparent bg-error px-4 py-2 text-sm font-bold text-on-primary transition duration-150 ease-in-out hover:bg-error/90 focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2 active:bg-error ${
                    disabled && 'opacity-50'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
