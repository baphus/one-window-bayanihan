import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function ServerError({ incidentId }) {
    return (
        <GuestLayout>
            <Head title="Something Went Wrong" />
            <div className="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
                <h1 className="text-6xl font-bold text-gray-300 mb-4">500</h1>
                <h2 className="text-2xl font-semibold text-gray-700 mb-2">Something Went Wrong</h2>
                <p className="text-gray-500 mb-8 max-w-md">
                    An unexpected error occurred. Please try again later.
                </p>
                <Link
                    href="/"
                    className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Go back home
                </Link>
                {incidentId && (
                    <p className="text-xs text-gray-400 mt-8">Ref: {incidentId}</p>
                )}
            </div>
        </GuestLayout>
    );
}
