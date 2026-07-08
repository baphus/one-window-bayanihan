import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Section from '@/Components/Section';
import { useRef, useEffect } from 'react';

const timezones = [
    'Asia/Manila', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Seoul',
    'Asia/Shanghai', 'Pacific/Guam', 'Australia/Sydney',
    'America/New_York', 'America/Los_Angeles', 'Europe/London', 'UTC',
];

function FieldLabel({ children }) {
    return (
        <p className="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 mb-1.5">
            {children}
        </p>
    );
}

export default function PersonalInfoForm({ data, setData, errors, onDirtyChange }) {
    const initialRef = useRef(structuredClone(data));

    const isDirty = data.name !== initialRef.current.name
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
        <Section
            title="Personal Information"
            description="Update your profile details and contact information."
        >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <FieldLabel>Name</FieldLabel>
                    <TextInput id="name" className="block w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required isFocused autoComplete="name" />
                    <InputError className="mt-1.5" message={errors.name} />
                </div>

                <div>
                    <FieldLabel>Email</FieldLabel>
                    <TextInput id="email" type="email" className="block w-full bg-slate-50 text-slate-500" value={data.email} readOnly autoComplete="username" />
                    <p className="mt-1 text-[11px] text-slate-400">
                        To change your email, use the Change Email section below.
                    </p>
                    <InputError className="mt-1.5" message={errors.email} />
                </div>

                <div>
                    <FieldLabel>Position / Title</FieldLabel>
                    <TextInput id="position" className="block w-full" value={data.position} onChange={(e) => setData('position', e.target.value)} placeholder="e.g. Case Management Officer" />
                    <InputError className="mt-1.5" message={errors.position} />
                </div>

                <div>
                    <FieldLabel>Department</FieldLabel>
                    <TextInput id="department" className="block w-full" value={data.department} onChange={(e) => setData('department', e.target.value)} placeholder="e.g. Migrant Services" />
                    <InputError className="mt-1.5" message={errors.department} />
                </div>

                <div>
                    <FieldLabel>Office Location</FieldLabel>
                    <TextInput id="office_location" className="block w-full" value={data.office_location} onChange={(e) => setData('office_location', e.target.value)} placeholder="e.g. Cebu City" />
                    <InputError className="mt-1.5" message={errors.office_location} />
                </div>

                <div>
                    <FieldLabel>Timezone</FieldLabel>
                    <select
                        id="timezone"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-[13px]"
                        value={data.timezone}
                        onChange={(e) => setData('timezone', e.target.value)}
                    >
                        {timezones.map((tz) => (
                            <option key={tz} value={tz}>{tz}</option>
                        ))}
                    </select>
                    <InputError className="mt-1.5" message={errors.timezone} />
                </div>
            </div>

            <div className="mt-5">
                <FieldLabel>Contact Number</FieldLabel>
                <TextInput id="contact_number" type="tel" className="block w-full md:w-1/2" value={data.contact_number} onChange={(e) => setData('contact_number', e.target.value)} placeholder="e.g. +63 912 345 6789" />
                <InputError className="mt-1.5" message={errors.contact_number} />
            </div>

            <div className="mt-5">
                <FieldLabel>Bio / About Me</FieldLabel>
                <textarea
                    id="bio"
                    rows={3}
                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-[13px] placeholder:text-slate-400"
                    value={data.bio}
                    onChange={(e) => setData('bio', e.target.value)}
                    placeholder="Brief description of your role and professional background..."
                />
                <InputError className="mt-1.5" message={errors.bio} />
            </div>

            <div className="mt-6 pt-5 border-t border-slate-200">
                <p className="text-[11px] font-extrabold uppercase tracking-wider text-slate-700 mb-3">
                    Emergency Contact
                </p>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <FieldLabel>Full Name</FieldLabel>
                        <TextInput id="emergency_contact_name" className="block w-full" value={data.emergency_contact?.name || ''} onChange={(e) => setData('emergency_contact.name', e.target.value)} placeholder="Full name" />
                        <InputError className="mt-1.5" message={errors['emergency_contact.name']} />
                    </div>
                    <div>
                        <FieldLabel>Relation</FieldLabel>
                        <TextInput id="emergency_contact_relation" className="block w-full" value={data.emergency_contact?.relation || ''} onChange={(e) => setData('emergency_contact.relation', e.target.value)} placeholder="e.g. Spouse, Parent" />
                        <InputError className="mt-1.5" message={errors['emergency_contact.relation']} />
                    </div>
                    <div>
                        <FieldLabel>Phone Number</FieldLabel>
                        <TextInput id="emergency_contact_phone" type="tel" className="block w-full" value={data.emergency_contact?.phone || ''} onChange={(e) => setData('emergency_contact.phone', e.target.value)} placeholder="e.g. +63 912 345 6789" />
                        <InputError className="mt-1.5" message={errors['emergency_contact.phone']} />
                    </div>
                </div>
            </div>
        </Section>
    );
}
