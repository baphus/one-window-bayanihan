import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function ReferralCreate({ case_id, agencies }) {
    const { data, setData, post, processing, errors } = useForm({
        case_id: case_id || '',
        agcy_id: '',
        required_services: '',
        notes: '',
    });

    function handleSubmit(e) {
        e.preventDefault();
        post(route('referrals.store'));
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Create New Referral
                </h2>
            }
        >
            <Head title="Create Referral" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit}>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-lg font-medium text-gray-900">Referral Details</h3>
                            </div>
                            <div className="space-y-4 px-6 py-4">
                                <div>
                                    <InputLabel htmlFor="case_id" value="Case" />
                                    {case_id ? (
                                        <TextInput
                                            id="case_id"
                                            type="text"
                                            className="mt-1 block w-full bg-gray-50"
                                            value={case_id}
                                            disabled
                                        />
                                    ) : (
                                        <TextInput
                                            id="case_id"
                                            type="text"
                                            className="mt-1 block w-full"
                                            placeholder="Enter Case ID"
                                            value={data.case_id}
                                            onChange={(e) => setData('case_id', e.target.value)}
                                        />
                                    )}
                                    <InputError message={errors.case_id} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="agcy_id" value="Agency *" />
                                    <select
                                        id="agcy_id"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.agcy_id}
                                        onChange={(e) => setData('agcy_id', e.target.value)}
                                    >
                                        <option value="">Select an agency...</option>
                                        {agencies.map((agency) => (
                                            <option key={agency.id} value={agency.id}>
                                                {agency.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.agcy_id} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="required_services" value="Required Services *" />
                                    <textarea
                                        id="required_services"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={4}
                                        value={data.required_services}
                                        onChange={(e) => setData('required_services', e.target.value)}
                                        placeholder="Describe the services required from this agency"
                                    />
                                    <InputError message={errors.required_services} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="notes" value="Additional Notes" />
                                    <textarea
                                        id="notes"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={3}
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                    />
                                    <InputError message={errors.notes} className="mt-2" />
                                </div>

                                <div className="flex items-center justify-end gap-4 pt-4">
                                    <Link
                                        href={route('referrals.index')}
                                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                    >
                                        Cancel
                                    </Link>
                                    <PrimaryButton disabled={processing}>
                                        Create Referral
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
