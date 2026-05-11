import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function TrackingVerify({ tracker_number, hint }) {
    const { data, setData, post, processing, errors } = useForm({
        tracker_number: tracker_number,
        otp: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('track.verify-otp'));
    }

    return (
        <GuestLayout>
            <Head title="Verify OTP" />

            <div className="flex min-h-screen items-center justify-center bg-gray-100 px-4">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold text-gray-900">Verify OTP</h1>
                        <p className="mt-2 text-sm text-gray-600">
                            Enter the 6-digit code sent to your registered contact.
                        </p>
                        <p className="mt-1 text-xs text-gray-400">
                            Tracker: {tracker_number}
                        </p>
                    </div>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="px-6 py-8">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="otp" value="OTP Code" />
                                    <TextInput
                                        id="otp"
                                        type="text"
                                        className="mt-1 block w-full text-center text-2xl tracking-widest"
                                        value={data.otp}
                                        onChange={(e) => setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                        placeholder="000000"
                                        maxLength={6}
                                    />
                                    <InputError message={errors.otp} className="mt-2" />
                                </div>

                                <PrimaryButton className="w-full justify-center" disabled={processing}>
                                    Verify & View Case
                                </PrimaryButton>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
