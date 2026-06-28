import { Head, Link } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';

export default function Forbidden() {
    return (
        <GuestLayout>
            <Head title="Access Denied" />
            <div className="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
                <h1 className="text-6xl font-bold text-gray-300 mb-4">403</h1>
                <h2 className="text-2xl font-semibold text-gray-700 mb-2">Access Denied</h2>
                <p className="text-gray-500 mb-8 max-w-md">
                    You don't have permission to access this resource.
                </p>
                <Link
                    href="/"
                    className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Go back home
                </Link>
            </div>
        </GuestLayout>
    );
}
