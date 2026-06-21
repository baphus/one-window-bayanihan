import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import Section from '@/Components/Section';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef, useEffect, useMemo } from 'react';
import { updatePasswordSchema } from '@/Schemas/profileSchemas';
import useClientValidation from '@/Hooks/useClientValidation';

export default function UpdatePasswordForm({ className = '', onDirtyChange, onBypass }) {
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

    const { validate } = useClientValidation(updatePasswordSchema, data, setError);

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
                    passwordInput.current.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
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
                <div>
                    <InputLabel
                        htmlFor="current_password"
                        value="Current Password"
                    />

                    <TextInput
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        required
                    />

                    <InputError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="New Password" />

                    <TextInput
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        required
                        minLength={8}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                    />

                    <TextInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        required
                        minLength={8}
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

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
