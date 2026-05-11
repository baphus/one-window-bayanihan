import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

export default function CaseCreate() {
    const { data, setData, post, processing, errors } = useForm({
        client_type: 'OFW',
        summary: '',
        client: {
            first_name: '',
            last_name: '',
            middle_name: '',
            suffix: '',
            date_of_birth: '',
            sex: '',
        },
        address: {
            line1: '',
            line2: '',
            city: '',
            province: '',
            postal_code: '',
            country: 'Philippines',
        },
        employment: {
            employer_name: '',
            position: '',
            country: '',
            start_date: '',
            end_date: '',
        },
    });

    function handleClientChange(field, value) {
        setData('client', { ...data.client, [field]: value });
    }

    function handleAddressChange(field, value) {
        setData('address', { ...data.address, [field]: value });
    }

    function handleEmploymentChange(field, value) {
        setData('employment', { ...data.employment, [field]: value });
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(route('cases.store'));
    }

    return (
        <AppLayout title="Create Case">
            <Head title="Create Case" />

            <div className="mb-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Create New Case</h1>
                        <p className="text-sm text-slate-500 mt-1">Fill in the details below to create a new case record.</p>
                    </div>
                </div>
            </div>

            <div className="mx-auto max-w-4xl">
                <form onSubmit={handleSubmit}>
                    <div className="space-y-6">
                        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Case Information</h3>
                            </div>
                            <div className="px-6 py-4">
                                <div className="mb-4">
                                    <InputLabel htmlFor="client_type" value="Client Type" />
                                    <select
                                        id="client_type"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={data.client_type}
                                        onChange={(e) => setData('client_type', e.target.value)}
                                    >
                                        <option value="OFW">Overseas Filipino Worker</option>
                                        <option value="NEXT_OF_KIN">Next of Kin</option>
                                    </select>
                                    <InputError message={errors.client_type} className="mt-2" />
                                </div>

                                <div className="mb-4">
                                    <InputLabel htmlFor="summary" value="Case Summary / Narrative" />
                                    <textarea
                                        id="summary"
                                        className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        rows={3}
                                        value={data.summary}
                                        onChange={(e) => setData('summary', e.target.value)}
                                    />
                                    <InputError message={errors.summary} className="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Client Profile</h3>
                            </div>
                            <div className="px-6 py-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="first_name" value="First Name *" />
                                        <TextInput
                                            id="first_name"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.client.first_name}
                                            onChange={(e) => handleClientChange('first_name', e.target.value)}
                                        />
                                        <InputError message={errors['client.first_name']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="last_name" value="Last Name *" />
                                        <TextInput
                                            id="last_name"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.client.last_name}
                                            onChange={(e) => handleClientChange('last_name', e.target.value)}
                                        />
                                        <InputError message={errors['client.last_name']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="middle_name" value="Middle Name" />
                                        <TextInput
                                            id="middle_name"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.client.middle_name}
                                            onChange={(e) => handleClientChange('middle_name', e.target.value)}
                                        />
                                        <InputError message={errors['client.middle_name']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="suffix" value="Suffix" />
                                        <TextInput
                                            id="suffix"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.client.suffix}
                                            onChange={(e) => handleClientChange('suffix', e.target.value)}
                                        />
                                        <InputError message={errors['client.suffix']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="date_of_birth" value="Date of Birth" />
                                        <TextInput
                                            id="date_of_birth"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.client.date_of_birth}
                                            onChange={(e) => handleClientChange('date_of_birth', e.target.value)}
                                        />
                                        <InputError message={errors['client.date_of_birth']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="sex" value="Sex" />
                                        <select
                                            id="sex"
                                            className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            value={data.client.sex}
                                            onChange={(e) => handleClientChange('sex', e.target.value)}
                                        >
                                            <option value="">Select...</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                        <InputError message={errors['client.sex']} className="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Address</h3>
                            </div>
                            <div className="px-6 py-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="sm:col-span-2">
                                        <InputLabel htmlFor="line1" value="Street Address" />
                                        <TextInput
                                            id="line1"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.line1}
                                            onChange={(e) => handleAddressChange('line1', e.target.value)}
                                        />
                                        <InputError message={errors['address.line1']} className="mt-2" />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <InputLabel htmlFor="line2" value="Street Address Line 2" />
                                        <TextInput
                                            id="line2"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.line2}
                                            onChange={(e) => handleAddressChange('line2', e.target.value)}
                                        />
                                        <InputError message={errors['address.line2']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="city" value="City/Municipality" />
                                        <TextInput
                                            id="city"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.city}
                                            onChange={(e) => handleAddressChange('city', e.target.value)}
                                        />
                                        <InputError message={errors['address.city']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="province" value="Province" />
                                        <TextInput
                                            id="province"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.province}
                                            onChange={(e) => handleAddressChange('province', e.target.value)}
                                        />
                                        <InputError message={errors['address.province']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="postal_code" value="Postal Code" />
                                        <TextInput
                                            id="postal_code"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.postal_code}
                                            onChange={(e) => handleAddressChange('postal_code', e.target.value)}
                                        />
                                        <InputError message={errors['address.postal_code']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="country" value="Country" />
                                        <TextInput
                                            id="country"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.address.country}
                                            onChange={(e) => handleAddressChange('country', e.target.value)}
                                        />
                                        <InputError message={errors['address.country']} className="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white shadow-sm border border-slate-200">
                            <div className="border-b border-slate-200 px-6 py-4">
                                <h3 className="text-lg font-semibold text-slate-900">Employment History</h3>
                            </div>
                            <div className="px-6 py-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="employer_name" value="Employer Name" />
                                        <TextInput
                                            id="employer_name"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.employment.employer_name}
                                            onChange={(e) => handleEmploymentChange('employer_name', e.target.value)}
                                        />
                                        <InputError message={errors['employment.employer_name']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="position" value="Position" />
                                        <TextInput
                                            id="position"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.employment.position}
                                            onChange={(e) => handleEmploymentChange('position', e.target.value)}
                                        />
                                        <InputError message={errors['employment.position']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="emp_country" value="Country of Employment" />
                                        <TextInput
                                            id="emp_country"
                                            type="text"
                                            className="mt-1 block w-full"
                                            value={data.employment.country}
                                            onChange={(e) => handleEmploymentChange('country', e.target.value)}
                                        />
                                        <InputError message={errors['employment.country']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="start_date" value="Start Date" />
                                        <TextInput
                                            id="start_date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.employment.start_date}
                                            onChange={(e) => handleEmploymentChange('start_date', e.target.value)}
                                        />
                                        <InputError message={errors['employment.start_date']} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="end_date" value="End Date" />
                                        <TextInput
                                            id="end_date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.employment.end_date}
                                            onChange={(e) => handleEmploymentChange('end_date', e.target.value)}
                                        />
                                        <InputError message={errors['employment.end_date']} className="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-4">
                            <Link
                                href={route('cases.index')}
                                className="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                            >
                                Cancel
                            </Link>
                            <PrimaryButton disabled={processing}>
                                Create Case
                            </PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
