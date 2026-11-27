import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import useSessionDraft from '@/Hooks/useSessionDraft';

const defaults = { name: '', email: '', message: '', cf_turnstile_response: '' };
const fields = ['name', 'email', 'message'];

afterEach(() => sessionStorage.clear());

describe('Contact session draft integration', () => {
  it('round-trips guest message fields only, preserves CAPTCHA key and clears on success', async () => {
    let state = { ...defaults };
    const setData = (next) => { state = next; };
    const { result, rerender } = renderHook(() => useSessionDraft({ userId: 'guest', formKey: 'contact', data: state, setData, defaults, fields }));
    await waitFor(() => expect(result.current.status).toBe(''));
    act(() => { state = { ...state, name: 'Guest', email: 'g@example.com', message: 'Help', cf_turnstile_response: 'secret' }; rerender(); });
    await waitFor(() => expect(result.current.status).toContain('Draft saved'));
    const saved = sessionStorage.getItem('owb_session_draft_v1_guest_contact_create');
    expect(saved).toContain('Help');
    expect(saved).not.toContain('secret');

    state = { ...defaults };
    const restored = renderHook(() => useSessionDraft({ userId: 'guest', formKey: 'contact', data: state, setData, defaults, fields }));
    await waitFor(() => expect(state).toMatchObject({ name: 'Guest', email: 'g@example.com', message: 'Help', cf_turnstile_response: '' }));
    act(() => restored.result.current.onSuccess());
    expect(sessionStorage.getItem('owb_session_draft_v1_guest_contact_create')).toBeNull();
    restored.unmount();
    rerender();
  });
});
