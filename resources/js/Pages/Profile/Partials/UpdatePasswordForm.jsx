import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import Section from '@/Components/Section';
import PasswordInput from '@/Components/PasswordInput';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { useRef, useEffect, useMemo } from 'react';
import { makeUpdatePasswordSchema } from '@/Schemas/profileSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function UpdatePasswordForm({ className = '', onDirtyChange, onBypass }) {
    const { passwordRules } = usePage().props;
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const initialRef = useRef({ current_password: '', password: '', password_confirmation: '' });

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
        setError,
        clearErrors,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const schema = useMemo(() => makeUpdatePasswordSchema(passwordRules), [passwordRules]);
    const { validate } = useClientValidation(schema, data, setError);

    const isDirty = useMemo(() => (
        data.current_password !== initialRef.current.current_password
        || data.password !== initialRef.current.password
        || data.password_confirmation !== initialRef.current.password_confirmation
    ), [data]);
    useEffect(() => { onDirtyChange?.(isDirty); }, [isDirty, onDirtyChange]);

    const updatePassword = (e) => {
        e.preventDefault();
        onBypass?.();

        clearErrors();
        if (!validate()) return;

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDirtyChange?.(false);
            },
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <Section
            title="Update Password"
            description="Ensure your account is using a long, random password to stay secure."
            className={className}
        >
            <form onSubmit={updatePassword} className="space-y-6">
                <PasswordInput
                    id="current_password"
                    label="Current Password"
                    value={data.current_password}
                    onChange={(v) => setData('current_password', v)}
                    error={errors.current_password}
                    autoComplete="current-password"
                />

                <PasswordInput
                    id="new_password"
                    label="New Password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    error={errors.password}
                    autoComplete="new-password"
                    showStrengthMeter
                    rules={passwordRules}
                    confirmation={data.password_confirmation}
                />

                <PasswordInput
                    id="password_confirmation"
                    label="Confirm Password"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                    autoComplete="new-password"
                />

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </Section>
    );
}
