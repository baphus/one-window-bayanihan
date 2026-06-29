import React from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function TooManyRequests() {
    return (
        <GuestLayout>
            <Head title="Too Many Requests" />
            <div className="flex min-h-full flex-col items-center justify-center">
                <div className="rounded-lg bg-white p-8 shadow-md text-center max-w-md">
                    <h1 className="text-6xl font-bold text-gray-300 mb-4">429</h1>
                    <h2 className="text-xl font-semibold text-gray-900 mb-2">Too Many Requests</h2>
                    <p className="text-gray-600 mb-6">Please wait before trying again.</p>
                    <Link
                        href="/"
                        className="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 transition-colors"
                    >
                        Go back home
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
