import { useForm } from '@inertiajs/react';
import OfwPortalLayout from '@/Layouts/OfwPortalLayout';
import useUnsavedChanges from '@/Hooks/useUnsavedChanges';
import ProfilePictureUpload from '@/Components/ProfilePictureUpload';
import { ProfileCard, ProfileField, ProfileSaveBar, ProfileSection, ProfileStaticField, ProfileTextInput } from '@/Components/ui/ProfileCard';
import IdentityFields, { emptyIdentity } from '@/Components/IdentityFields';
import ChangeEmailForm from '@/Pages/Profile/Partials/ChangeEmailForm';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm';
import MfaSetup from '@/Components/MfaSetup';
import { useToast } from '@/Hooks/useToast';
import safeRoute from '@/utils/safeRoute';

const Field = ProfileField;
const TextInput = ProfileTextInput;

export default function Profile({ user, mfaEnabled = false, mfaRequired = false, email_change_step, email_change_hint }) {
    const toast = useToast();
    const form = useForm({ ...emptyIdentity(user), contact_number: user?.contact_number ?? '' });
    const { UnsavedModal, bypassNext } = useUnsavedChanges(form.isDirty);

    function save(event) {
        event.preventDefault();
        form.clearErrors();
        bypassNext();
        form.put(safeRoute('ofw.profile.update', undefined, '/my-cases/profile'), {
            preserveScroll: true,
            onSuccess: () => { toast.success('Profile updated. Existing case records are unchanged.'); },
            onError: (errors) => { const first = Object.values(errors)[0]; if (first) toast.error(String(first)); },
        });
    }

    return (
        <OfwPortalLayout>
            <ProfileCard title="Account settings" description="Manage your identity, contact and sign-in details.">
                <form onSubmit={save}>
                    <ProfileSection title="Account" description="Your account identity and profile photo.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <IdentityFields data={form.data} setData={form.setData} errors={form.errors} requireDemographics />
                            </div>
                            <ProfileStaticField label="Email" value={user?.email || '—'} />
                            <p className="text-sm text-slate-600 sm:col-span-2">Editing these details updates your account only. Names and facts already recorded on your cases stay as filed.</p>
                            <div className="sm:col-span-2"><ProfilePictureUpload currentUrl={user?.avatar_url ?? null} uploadRoute={safeRoute('ofw.profile.avatar.store', undefined, '/my-cases/profile/avatar')} destroyRoute={safeRoute('ofw.profile.avatar.destroy', undefined, '/my-cases/profile/avatar')} /></div>
                        </div>
                    </ProfileSection>
                    <ProfileSection title="Contact" description="Change the phone number used for account contact.">
                        <Field label="Contact number" htmlFor="account-contact" error={form.errors.contact_number}>
                            <TextInput id="account-contact" type="tel" autoComplete="tel" maxLength={50} value={form.data.contact_number} onChange={(event) => form.setData('contact_number', event.target.value)} />
                        </Field>
                    </ProfileSection>
                    <ProfileSaveBar processing={form.processing} dirty={form.isDirty} onDiscard={() => form.reset()} />
                </form>
            </ProfileCard>
            <div className="mt-6 space-y-6">
                <ChangeEmailForm initialStep={email_change_step} hint={email_change_hint} />
                <UpdatePasswordForm onBypass={bypassNext} />
                <MfaSetup mfaEnabled={mfaEnabled} mfaRequired={mfaRequired} />
                <DeleteUserForm onBypass={bypassNext} description="Deleting your account deactivates access. Official case, document, message, and audit records are retained. Enter your password to confirm." confirmationDescription="Your account access will be deactivated. Official case, document, message, and audit records will be retained. Enter your password to confirm." />
            </div>
            {UnsavedModal}
        </OfwPortalLayout>
    );
}
