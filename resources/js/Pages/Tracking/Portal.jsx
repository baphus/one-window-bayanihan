import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function TrackingPortal() {
    const { data, setData, post, processing, errors } = useForm({
        tracker_number: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('track.send-otp'));
    }

    return (
        <GuestLayout>
            <Head title="Track Your Case" />

            <div className="flex min-h-screen items-center justify-center bg-gray-100 px-4">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold text-gray-900">Track Your Case</h1>
                        <p className="mt-2 text-sm text-gray-600">
                            Enter your tracker number to check the status of your case.
                        </p>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="px-6 py-8">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="tracker_number" value="Tracker Number" />
                                    <TextInput
                                        id="tracker_number"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={data.tracker_number}
                                        onChange={(e) => setData('tracker_number', e.target.value)}
                                        placeholder="e.g. TRK-XXXXXXXXXX"
                                    />
                                    <InputError message={errors.tracker_number} className="mt-2" />
                                </div>

                                <PrimaryButton className="w-full justify-center" disabled={processing}>
                                    Send OTP
                                </PrimaryButton>
                            </form>

                            <p className="mt-4 text-center text-xs text-gray-500">
                                An OTP will be sent to the email address associated with your case.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
