import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-primary text-on-surface focus:border-primary-container'
                    : 'border-transparent text-on-surface-variant hover:border-outline-variant hover:text-on-surface focus:border-outline-variant focus:text-on-surface') +
                className
            }
        >
            {children}
        </Link>
    );
}
