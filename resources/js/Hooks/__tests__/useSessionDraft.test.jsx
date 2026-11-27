import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import useSessionDraft, { removeSessionDrafts } from '../useSessionDraft';

const defaults = { name: '', count: 0 };
const fields = ['name', 'count'];
function setup(userId = 'u1', formKey = 'case', recordId = 'create') {
    const hook = renderHook((props) => {
        const [data, setData] = React.useState(defaults);
        return { draft: useSessionDraft({ ...props, defaults, fields, data, setData }), data, setData };
    }, { initialProps: { userId, formKey, recordId } });
    return {
        ...hook,
        edit(next) { act(() => hook.result.current.setData((value) => ({ ...value, ...next }))); },
        value: () => hook.result.current.data,
        draft: () => hook.result.current.draft,
    };
}

import React from 'react';
let setItemOriginal;
beforeEach(() => sessionStorage.clear());
afterEach(() => {
    if (setItemOriginal) Storage.prototype.setItem = setItemOriginal;
    setItemOriginal = null;
    sessionStorage.clear();
});

describe('useSessionDraft', () => {
    it('restores edits after unmount and remount, scoped by account, form, and record', async () => {
        const first = setup();
        first.edit({ name: 'Saved' });
        await waitFor(() => expect(sessionStorage.length).toBe(1));
        first.unmount();
        const restored = setup();
        await waitFor(() => expect(restored.value().name).toBe('Saved'));
        const otherAccount = setup('u2');
        await waitFor(() => expect(otherAccount.value()).toEqual(defaults));
        const otherForm = setup('u1', 'other');
        await waitFor(() => expect(otherForm.value()).toEqual(defaults));
        const otherRecord = setup('u1', 'case', '42');
        await waitFor(() => expect(otherRecord.value()).toEqual(defaults));
    });

    it('clears, saves later edits, removes successful draft, then saves later edits', async () => {
        const hook = setup();
        hook.edit({ name: 'One' });
        await waitFor(() => expect(sessionStorage.length).toBe(1));
        act(() => hook.draft().clear());
        await waitFor(() => expect(hook.value()).toEqual(defaults));
        expect(sessionStorage.length).toBe(0);
        hook.edit({ name: 'Two' });
        await waitFor(() => expect(sessionStorage.length).toBe(1));
        act(() => hook.draft().onSuccess());
        expect(sessionStorage.length).toBe(0);
        hook.edit({ name: 'Three' });
        await waitFor(() => expect(sessionStorage.length).toBe(1));
    });

    it('does not persist defaults and safely handles malformed and blocked storage', async () => {
        const initial = setup();
        await waitFor(() => expect(initial.value()).toEqual(defaults));
        expect(sessionStorage.length).toBe(0);
        initial.unmount();
        Storage.prototype.setItem.call(sessionStorage, 'owb_session_draft_v1_u1_case_create', '{bad');
        const malformed = setup();
        await waitFor(() => expect(malformed.value()).toEqual(defaults));
        malformed.unmount();
        setItemOriginal = Storage.prototype.setItem;
        Storage.prototype.setItem = () => { throw new DOMException('blocked'); };
        const blocked = setup('u3');
        blocked.edit({ name: 'Blocked' });
        await waitFor(() => expect(blocked.draft().status).toMatch(/unavailable/));
    });

    it('does not overwrite preloaded drafts during hydration or write old context data into switched keys', async () => {
        const key = 'owb_session_draft_v1_u1_case_create';
        sessionStorage.setItem(key, JSON.stringify({ version: 1, data: { name: 'Restored', count: 4 } }));
        const hook = setup();
        await waitFor(() => expect(hook.value()).toEqual({ name: 'Restored', count: 4 }));
        expect(JSON.parse(sessionStorage.getItem(key)).data).toEqual({ name: 'Restored', count: 4 });
        hook.rerender();
        hook.rerender({ userId: 'u2', formKey: 'case', recordId: 'create' });
        await waitFor(() => expect(hook.result.current.data).toEqual(defaults));
        expect(sessionStorage.getItem('owb_session_draft_v1_u2_case_create')).toBeNull();
        hook.unmount();
    });

    it('keeps restored shapes, strips unknown nested keys, and suppresses reset after success', async () => {
        const shape = { address: { street: '' }, labels: [''] };
        const key = 'owb_session_draft_v1_u4_profile_create';
        sessionStorage.setItem(key, JSON.stringify({ version: 1, data: { address: 'wrong', labels: ['ok'], ignored: true } }));
        const hook = renderHook(() => {
            const [data, setData] = React.useState(shape);
            return { data, setData, draft: useSessionDraft({ userId: 'u4', formKey: 'profile', data, setData, defaults: shape, fields: ['address', 'labels'] }) };
        });
        await waitFor(() => expect(hook.result.current.data).toEqual({ address: { street: '' }, labels: ['ok'] }));
        act(() => hook.result.current.draft.onSuccess());
        act(() => hook.result.current.setData(shape));
        await waitFor(() => expect(sessionStorage.getItem(key)).toBeNull());
        act(() => hook.result.current.setData({ ...shape, address: { street: 'Next' } }));
        await waitFor(() => expect(JSON.parse(sessionStorage.getItem(key)).data.address.street).toBe('Next'));
    });

    it('restores empty-default arrays and nullable primitive IDs; omits nested files', async () => {
        const shape = { category_ids: [], agency_id: null, answers: [{ id: '', value: '' }] };
        const key = 'owb_session_draft_v1_u5_options_create';
        const data = { category_ids: ['c1'], agency_id: 'a1', answers: [{ id: 'q1', value: 'yes', file: new File(['x'], 'secret.txt'), unknown: 'ignore' }] };
        const projected = { category_ids: data.category_ids, agency_id: data.agency_id, answers: [{ id: 'q1', value: 'yes' }] };
        sessionStorage.setItem(key, JSON.stringify({ version: 1, data: projected }));
        const hook = renderHook(() => {
            const [value, setValue] = React.useState(shape);
            return { value, setValue, draft: useSessionDraft({ userId: 'u5', formKey: 'options', data: value, setData: setValue, defaults: shape, fields: ['category_ids', 'agency_id', 'answers'] }) };
        });
        await waitFor(() => expect(hook.result.current.value).toEqual(projected));
        act(() => hook.result.current.setValue(data));
        await waitFor(() => expect(JSON.parse(sessionStorage.getItem(key)).data).toEqual(projected));
    });

    it('does not overwrite restored draft when clear runs before hydration effect', async () => {
        const key = 'owb_session_draft_v1_u6_case_create';
        sessionStorage.setItem(key, JSON.stringify({ version: 1, data: { name: 'Restore', count: 2 } }));
        const hook = setup('u6');
        act(() => hook.draft().clear());
        await waitFor(() => expect(hook.value()).toEqual(defaults));
        expect(sessionStorage.getItem(key)).toBeNull();
    });

    it('rejects oversized allowlisted arrays without truncating or claiming saved', async () => {
        const shape = { ids: [] };
        const hook = renderHook(() => {
            const [data, setData] = React.useState(shape);
            return { data, setData, draft: useSessionDraft({ userId: 'u7', formKey: 'case', data, setData, defaults: shape, fields: ['ids'] }) };
        });
        act(() => hook.result.current.setData({ ids: Array.from({ length: 101 }, (_, index) => String(index)) }));
        await waitFor(() => expect(hook.result.current.draft.status).toMatch(/unavailable/));
        expect(sessionStorage.getItem('owb_session_draft_v1_u7_case_create')).toBeNull();
        expect(hook.result.current.data.ids).toHaveLength(101);
    });

    it('uses explicit shapes to validate object arrays with empty defaults', async () => {
        const shape = { requirements: [] };
        const key = 'owb_session_draft_v1_u8_service_create';
        const valid = [{ id: 'r1', name: 'Safety', is_required: true }];
        sessionStorage.setItem(key, JSON.stringify({ version: 1, data: { requirements: [{ ...valid[0], secret: 'omit', file: 'not-a-file' }] } }));
        const hook = renderHook(() => {
            const [data, setData] = React.useState(shape);
            return { data, setData, draft: useSessionDraft({ userId: 'u8', formKey: 'service', data, setData, defaults: shape, shapes: { requirements: [{ id: '', name: '', is_required: true }] }, fields: ['requirements'] }) };
        });
        await waitFor(() => expect(hook.result.current.data).toEqual({ requirements: valid }));
        act(() => hook.result.current.setData({ requirements: [{ ...valid[0], secret: 'omit', file: new File(['x'], 'secret.txt') }] }));
        await waitFor(() => expect(JSON.parse(sessionStorage.getItem(key)).data).toEqual({ requirements: valid }));
    });

    it('removes only generic drafts during logout cleanup', () => {
        sessionStorage.setItem('owb_session_draft_v1_x_y_z', 'data');
        sessionStorage.setItem('owb_intake_session_v1_x', 'preserve');
        removeSessionDrafts();
        expect(sessionStorage.getItem('owb_session_draft_v1_x_y_z')).toBeNull();
        expect(sessionStorage.getItem('owb_intake_session_v1_x')).toBe('preserve');
    });
});
