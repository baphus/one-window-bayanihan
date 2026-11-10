import { useMemo } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { z } from 'zod';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import PasswordInput from '@/Components/PasswordInput';
import createPasswordSchema from '@/utils/createPasswordSchema';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import { ProfileCard, ProfileField, ProfileSaveBar, ProfileSection, ProfileStaticField, ProfileTextInput } from '@/Components/ui/ProfileCard';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';

const Field = ProfileField;
const TextInput = ProfileTextInput;

export default function Profile({ user }) {
    const { passwordRules } = usePage().props;
    const toast = useToast();
    const form = useForm({ contact_number: user?.contact_number ?? '', current_password: '', password: '', password_confirmation: '' });
    const passwordSchema = useMemo(() => createPasswordSchema(passwordRules), [passwordRules]);
    const hasDirty = form.isDirty;
    const { UnsavedModal, bypassNext } = useUnsavedChanges(hasDirty);

    function save(event) {
        event.preventDefault();
        form.clearErrors();
        if (form.data.password) {
            const result = z.object({ current_password: z.string().min(1, 'Enter your current password.'), password: passwordSchema, password_confirmation: z.string().min(1).refine((value) => value === form.data.password, 'Passwords do not match.') }).safeParse(form.data);
            if (!result.success) {
                result.error.issues.forEach((issue) => form.setError(issue.path[0], issue.message));
                return;
            }
        }
        bypassNext();
        form.transform(({ contact_number, current_password, password, password_confirmation }) => ({
            contact_number,
            ...(password ? { current_password, password, password_confirmation } : {}),
        }));
        form.put(safeRoute('ofw.profile.update', undefined, '/my-cases/profile'), {
            preserveScroll: true,
            onSuccess: () => { form.reset('current_password', 'password', 'password_confirmation'); toast.success('Account settings updated.'); },
            onError: (errors) => { const first = Object.values(errors)[0]; if (first) toast.error(String(first)); },
            onFinish: () => form.transform((values) => values),
        });
    }

    return (
        <OfwPortalLayout>
            <ProfileCard title="Account settings" description="Manage contact and sign-in details for your account.">
                <form onSubmit={save}>
                    <ProfileSection title="Account" description="Your account identity and profile photo.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <ProfileStaticField label="Name" value={user?.name || '—'} />
                            <ProfileStaticField label="Email" value={user?.email || '—'} />
                            <div className="sm:col-span-2"><ProfilePictureUpload currentUrl={user?.avatar_url ?? null} uploadRoute={safeRoute('ofw.profile.avatar.store', undefined, '/my-cases/profile/avatar')} destroyRoute={safeRoute('ofw.profile.avatar.destroy', undefined, '/my-cases/profile/avatar')} /></div>
                        </div>
                    </ProfileSection>
                    <ProfileSection title="Contact" description="Change the phone number used for account contact.">
                        <Field label="Contact number" htmlFor="account-contact" error={form.errors.contact_number}>
                            <TextInput id="account-contact" type="tel" autoComplete="tel" maxLength={50} value={form.data.contact_number} onChange={(event) => form.setData('contact_number', event.target.value)} />
                        </Field>
                    </ProfileSection>
                    <ProfileSection title="Password" description="Leave password fields empty to keep current password.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Current password" htmlFor="account-current-password" error={form.errors.current_password}><PasswordInput id="account-current-password" label="Current password" value={form.data.current_password} onChange={(value) => form.setData('current_password', value)} error={form.errors.current_password} autoComplete="current-password" /></Field>
                            <Field label="New password" htmlFor="account-new-password" error={form.errors.password}><PasswordInput id="account-new-password" label="New password" value={form.data.password} onChange={(value) => form.setData('password', value)} error={form.errors.password} autoComplete="new-password" rules={passwordRules} confirmation={form.data.password_confirmation} /></Field>
                            <Field label="Confirm new password" htmlFor="account-confirm-password" error={form.errors.password_confirmation}><PasswordInput id="account-confirm-password" label="Confirm new password" value={form.data.password_confirmation} onChange={(value) => form.setData('password_confirmation', value)} error={form.errors.password_confirmation} autoComplete="new-password" /></Field>
                        </div>
                    </ProfileSection>
                    <ProfileSaveBar processing={form.processing} dirty={form.isDirty} onDiscard={() => form.reset()} />
                </form>
            </ProfileCard>
            {UnsavedModal}
        </OfwPortalLayout>
    );
}
