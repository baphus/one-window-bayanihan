import { useEffect, useRef, useState } from 'react';

const VERSION = 1;
const STRINGS = ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex', 'contact_number', 'civil_status', 'civil_status_other', 'deployment_mode', 'employment_status', 'employment_employer_name', 'employment_employer_address', 'employment_position', 'employment_country', 'employment_last_position', 'employment_date_of_arrival', 'vulnerability_indicator', 'summary', 'proposed_assessment_other', 'return_reason'];
const ARRAYS = ['proposed_assessments'];
const ADDRESS = ['region', 'province', 'city_municipality', 'barangay', 'street'];
const KEY = (id) => `owb_intake_session_v${VERSION}_${id}`;
const snapshot = (data) => JSON.stringify({ ...Object.fromEntries([...STRINGS, ...ARRAYS].map((field) => [field, data[field]])), address: Object.fromEntries(ADDRESS.map((field) => [field, data.address?.[field] ?? ''])) });

function restore(key, initial) {
    try {
        const raw = sessionStorage.getItem(key);
        if (!raw || raw.length > 30000) return initial;
        const saved = JSON.parse(raw);
        if (!saved || typeof saved !== 'object' || saved.version !== VERSION || !saved.data || typeof saved.data !== 'object') return initial;
        const value = { ...initial, address: { ...initial.address } };
        for (const field of STRINGS) if (typeof saved.data[field] === 'string' && saved.data[field].length <= 5000) value[field] = saved.data[field];
        for (const field of ARRAYS) if (Array.isArray(saved.data[field]) && saved.data[field].length <= 50 && saved.data[field].every((item) => typeof item === 'string' && item.length <= 255)) value[field] = saved.data[field];
        if (saved.data.address && typeof saved.data.address === 'object' && !Array.isArray(saved.data.address)) {
            for (const field of ADDRESS) if (typeof saved.data.address[field] === 'string' && saved.data.address[field].length <= 1000) value.address[field] = saved.data.address[field];
        }
        return value;
    } catch { return initial; }
}

export function persistIntakeDraft(key, data) {
    try {
        const value = Object.fromEntries([...STRINGS, ...ARRAYS].map((field) => [field, data[field]]));
        value.address = Object.fromEntries(ADDRESS.map((field) => [field, data.address?.[field] ?? '']));
        const raw = JSON.stringify({ version: VERSION, data: value });
        if (raw.length > 30000) return false;
        sessionStorage.setItem(key, raw);
        return true;
    } catch { return false; }
}

export function removeIntakeDrafts() {
    try {
        const keys = [];
        for (let index = 0; index < sessionStorage.length; index++) {
            const key = sessionStorage.key(index);
            if (key?.startsWith('owb_intake_session_v')) keys.push(key);
        }
        keys.forEach((key) => sessionStorage.removeItem(key));
    } catch { /* Best effort cleanup. */ }
}

export default function useIntakeSessionDraft(userId, initialData, data, setData) {
    const key = userId == null || userId === '' ? null : KEY(userId);
    const [status, setStatus] = useState('');
    const hydrated = useRef(null);
    const pendingKey = useRef(null);
    const skipData = useRef(null);
    const skipSnapshot = useRef(null);
    const submitted = useRef(false);

    useEffect(() => {
        hydrated.current = null;
        pendingKey.current = key;
        submitted.current = false;
        if (!key) { setData(initialData); setStatus(''); return; }
        const restored = restore(key, initialData);
        skipSnapshot.current = snapshot(restored);
        setData(restored);
        setStatus('');
    }, [key]);

    useEffect(() => {
        if (pendingKey.current !== key || !key || submitted.current) return;
        if (hydrated.current !== key) {
            if (skipSnapshot.current !== snapshot(data)) return;
            hydrated.current = key;
            skipSnapshot.current = null;
            return;
        }
        if (skipSnapshot.current === snapshot(data)) { skipSnapshot.current = null; return; }
        const saved = persistIntakeDraft(key, data);
        setStatus(saved ? 'Draft saved in this tab. Uploaded files are not saved.' : 'Draft saving unavailable. Keep this tab open until you submit.');
    }, [data, key]);

    function clear(resetForm) {
        skipSnapshot.current = snapshot(initialData);
        submitted.current = false;
        try { if (key) sessionStorage.removeItem(key); } catch { /* Best effort cleanup. */ }
        if (resetForm) resetForm(initialData);
        else setData(initialData);
        setStatus('Draft cleared. Uploaded files must be selected again.');
    }
    function markSubmitted() {
        submitted.current = true;
        try { if (key) sessionStorage.removeItem(key); } catch { /* Best effort cleanup. */ }
        setStatus('');
    }
    return { clear, submitted: markSubmitted, status };
}
