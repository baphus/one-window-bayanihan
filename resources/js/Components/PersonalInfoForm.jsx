import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { useRef, useEffect } from 'react';

const timezones = [
    'Asia/Manila', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Seoul',
    'Asia/Shanghai', 'Pacific/Guam', 'Australia/Sydney',
    'America/New_York', 'America/Los_Angeles', 'Europe/London', 'UTC',
];

export default function PersonalInfoForm({ data, setData, errors, onDirtyChange }) {
    const initialRef = useRef(structuredClone(data));

    const isDirty = data.name !== initialRef.current.name
        || data.email !== initialRef.current.email
        || data.position !== initialRef.current.position
        || data.department !== initialRef.current.department
        || data.office_location !== initialRef.current.office_location
        || data.bio !== initialRef.current.bio
        || data.timezone !== initialRef.current.timezone
        || data.contact_number !== initialRef.current.contact_number
        || data.emergency_contact?.name !== initialRef.current.emergency_contact?.name
        || data.emergency_contact?.relation !== initialRef.current.emergency_contact?.relation
        || data.emergency_contact?.phone !== initialRef.current.emergency_contact?.phone;

    useEffect(() => { onDirtyChange?.(isDirty); }, [isDirty, onDirtyChange]);

    return (
        <section>
            <header>
                <h2 className="text-lg font-medium text-gray-900">Personal Information</h2>
                <p className="mt-1 text-sm text-gray-600">Update your profile details and contact information.</p>
            </header>

            <div className="mt-6 space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput id="name" className="mt-1 block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required isFocused autoComplete="name" />
                        <InputError className="mt-2" message={errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput id="email" type="email" className="mt-1 block w-full" value={data.email} onChange={(e) => setData('email', e.target.value)} required autoComplete="username" />
                        <InputError className="mt-2" message={errors.email} />
                    </div>

                    <div>
                        <InputLabel htmlFor="position" value="Position / Title" />
                        <TextInput id="position" className="mt-1 block w-full" value={data.position} onChange={(e) => setData('position', e.target.value)} />
                        <InputError className="mt-2" message={errors.position} />
                    </div>

                    <div>
                        <InputLabel htmlFor="department" value="Department" />
                        <TextInput id="department" className="mt-1 block w-full" value={data.department} onChange={(e) => setData('department', e.target.value)} />
                        <InputError className="mt-2" message={errors.department} />
                    </div>

                    <div>
                        <InputLabel htmlFor="office_location" value="Office Location" />
                        <TextInput id="office_location" className="mt-1 block w-full" value={data.office_location} onChange={(e) => setData('office_location', e.target.value)} />
                        <InputError className="mt-2" message={errors.office_location} />
                    </div>

                    <div>
                        <InputLabel htmlFor="timezone" value="Timezone" />
                        <select
                            id="timezone"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                            value={data.timezone}
                            onChange={(e) => setData('timezone', e.target.value)}
                        >
                            {timezones.map((tz) => (
                                <option key={tz} value={tz}>{tz}</option>
                            ))}
                        </select>
                        <InputError className="mt-2" message={errors.timezone} />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="contact_number" value="Contact Number" />
                    <TextInput id="contact_number" type="tel" className="mt-1 block w-full" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} />
                    <InputError className="mt-2" message={errors.contact_number} />
                </div>

                <div>
                    <InputLabel htmlFor="bio" value="Bio / About Me" />
                    <textarea
                        id="bio"
                        rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                        value={data.bio}
                        onChange={(e) => setData('bio', e.target.value)}
                        placeholder="Tell us a little about yourself..."
                    />
                    <InputError className="mt-2" message={errors.bio} />
                </div>

                <div>
                    <h3 className="text-sm font-medium text-gray-900 mb-3">Emergency Contact</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <InputLabel htmlFor="emergency_contact_name" value="Full Name" />
                            <TextInput id="emergency_contact_name" className="mt-1 block w-full" value={data.emergency_contact?.name || ''} onChange={(e) => setData('emergency_contact.name', e.target.value)} />
                            <InputError className="mt-2" message={errors['emergency_contact.name']} />
                        </div>
                        <div>
                            <InputLabel htmlFor="emergency_contact_relation" value="Relation" />
                            <TextInput id="emergency_contact_relation" className="mt-1 block w-full" value={data.emergency_contact?.relation || ''} onChange={(e) => setData('emergency_contact.relation', e.target.value)} />
                            <InputError className="mt-2" message={errors['emergency_contact.relation']} />
                        </div>
                        <div>
                            <InputLabel htmlFor="emergency_contact_phone" value="Phone Number" />
                            <TextInput id="emergency_contact_phone" type="tel" className="mt-1 block w-full" value={data.emergency_contact?.phone || ''} onChange={(e) => setData('emergency_contact.phone', e.target.value)} />
                            <InputError className="mt-2" message={errors['emergency_contact.phone']} />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
