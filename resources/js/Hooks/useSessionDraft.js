import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const VERSION = 1;
const PREFIX = `owb_session_draft_v${VERSION}_`;
const MAX_BYTES = 64 * 1024;
const MAX_DEPTH = 8;
const OMIT = new Set(['__proto__', 'prototype', 'constructor']);

function clean(value, depth = 0) {
    if (depth > MAX_DEPTH || (typeof Blob !== 'undefined' && value instanceof Blob)) return undefined;
    if (value === null || typeof value === 'boolean') return value;
    if (typeof value === 'string') return value.length <= 10000 ? value : undefined;
    if (typeof value === 'number') return Number.isFinite(value) ? value : undefined;
    if (Array.isArray(value)) {
        if (value.length > 100) return undefined;
        const items = value.map((item) => clean(item, depth + 1));
        return items.some((item) => item === undefined) ? undefined : items;
    }
    if (value && Object.getPrototypeOf(value) === Object.prototype) {
        const entries = [];
        for (const [key, item] of Object.entries(value)) {
            if (OMIT.has(key)) continue;
            const safe = clean(item, depth + 1);
            if (safe !== undefined) entries.push([key, safe]);
        }
        return Object.fromEntries(entries);
    }
    return undefined;
}

function conform(value, shape, depth = 0) {
    if (depth > MAX_DEPTH) return undefined;
    if (shape === null) return value === null || ['string', 'number', 'boolean'].includes(typeof value) ? value : undefined;
    if (Array.isArray(shape)) {
        if (!Array.isArray(value)) return undefined;
        if (shape.length === 0) return value.every((item) => ['string', 'number', 'boolean'].includes(typeof item) || item === null) ? clean(value, depth) : undefined;
        if (value.length > 100) return undefined;
        const result = value.map((item) => conform(item, shape[0], depth + 1));
        return result.some((item) => item === undefined) ? undefined : result;
    }
    if (shape && typeof shape === 'object') {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return undefined;
        const result = {};
        for (const [key, exemplar] of Object.entries(shape)) {
            if (Object.hasOwn(value, key)) {
                const item = conform(value[key], exemplar, depth + 1);
                if (item !== undefined) result[key] = item;
            }
        }
        return result;
    }
    return typeof value === typeof shape && (typeof value !== 'number' || Number.isFinite(value)) ? value : undefined;
}

const snapshot = (value) => JSON.stringify(value);
const makeKey = (userId, formKey, recordId) => `${PREFIX}${encodeURIComponent(String(userId))}_${encodeURIComponent(formKey)}_${encodeURIComponent(String(recordId))}`;

export function removeSessionDrafts() {
    try {
        const keys = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key?.startsWith(PREFIX)) keys.push(key);
        }
        keys.forEach((key) => sessionStorage.removeItem(key));
    } catch { /* Best-effort logout cleanup. */ }
}

export default function useSessionDraft({ userId, formKey, recordId = 'create', data, setData, defaults, fields, shapes = {} }) {
    const key = userId == null || userId === '' || !formKey ? null : makeKey(userId, formKey, recordId);
    const [status, setStatus] = useState('');
    const context = useRef(null);
    const expectedSnapshot = useRef(null);
    const savedSnapshot = useRef(null);
    const successSnapshot = useRef(null);
    const selected = useMemo(() => Object.fromEntries(fields.map((field) => [field, data?.[field]])), [data, fields]);
    const currentSnapshot = snapshot(clean(selected));

    useEffect(() => {
        context.current = key;
        expectedSnapshot.current = null;
        savedSnapshot.current = null;
        successSnapshot.current = null;
        if (!key) { setStatus(''); return; }
        let restored = defaults;
        try {
            const raw = sessionStorage.getItem(key);
            if (raw && raw.length <= MAX_BYTES) {
                const parsed = JSON.parse(raw);
                if (parsed?.version === VERSION && parsed.data && typeof parsed.data === 'object') {
                    const safe = clean(parsed.data);
                    if (safe) restored = { ...defaults, ...Object.fromEntries(fields.filter((field) => Object.hasOwn(safe, field)).map((field) => [field, conform(safe[field], Object.hasOwn(shapes, field) ? shapes[field] : defaults?.[field])]).filter(([, value]) => value !== undefined)) };
                }
            }
        } catch { /* Storage unavailable or malformed; continue with defaults. */ }
        expectedSnapshot.current = snapshot(clean(Object.fromEntries(fields.map((field) => [field, restored?.[field]]))));
        setData(restored);
        setStatus('');
    }, [key]);

    useEffect(() => {
        if (!key || context.current !== key || expectedSnapshot.current === null) return;
        if (currentSnapshot !== expectedSnapshot.current) return;
        expectedSnapshot.current = null;
        savedSnapshot.current = currentSnapshot;
    }, [currentSnapshot, key]);

    useEffect(() => {
        if (!key || context.current !== key || expectedSnapshot.current !== null || savedSnapshot.current === null) return;
        if (currentSnapshot === savedSnapshot.current) return;
        savedSnapshot.current = currentSnapshot;
        if (successSnapshot.current !== null) {
            if (currentSnapshot === successSnapshot.current) return;
            successSnapshot.current = null;
        }
        let cleaned;
        try {
            const conformed = fields.map((field) => [field, conform(clean(selected[field]), Object.hasOwn(shapes, field) ? shapes[field] : defaults?.[field])]);
            if (conformed.some(([field, value]) => selected[field] !== undefined && value === undefined)) throw new Error('invalid field');
            cleaned = Object.fromEntries(conformed.filter(([, value]) => value !== undefined));
            const isEmpty = fields.every((field) => snapshot(cleaned?.[field]) === snapshot(clean(defaults?.[field])));
            if (isEmpty) { sessionStorage.removeItem(key); setStatus(''); return; }
            const raw = JSON.stringify({ version: VERSION, data: cleaned });
            if (raw.length > MAX_BYTES) throw new Error('oversized');
            sessionStorage.setItem(key, raw);
            setStatus('Draft saved in this tab. Uploaded files are not saved.');
        } catch { setStatus('Draft saving unavailable. Keep this tab open until you submit.'); }
    }, [currentSnapshot, key]);

    const clear = useCallback(() => {
        try { if (key) sessionStorage.removeItem(key); } catch { /* Best effort. */ }
        successSnapshot.current = null;
        const reset = { ...defaults };
        expectedSnapshot.current = null;
        savedSnapshot.current = snapshot(clean(Object.fromEntries(fields.map((field) => [field, reset[field]]))));
        setData(reset);
        setStatus('Draft cleared. Uploaded files must be selected again.');
    }, [key, defaults, fields, setData]);

    const onSuccess = useCallback(() => {
        const current = snapshot(clean(Object.fromEntries(fields.map((field) => [field, data?.[field]]))));
        successSnapshot.current = current;
        savedSnapshot.current = current;
        try { if (key) sessionStorage.removeItem(key); } catch { /* Best effort. */ }
        setStatus('');
    }, [key, data, fields]);
    return { clear, onSuccess, status };
}
