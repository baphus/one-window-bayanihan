import { useEffect, useRef, useState, useCallback } from 'react';

/**
 * useAutoSave — debounced field-change auto-save + page-unload save
 * for the case creation draft system.
 *
 * @param {object}  params
 * @param {object}  params.formData  — The current form data (deeply nested object)
 * @param {string|null} params.draftId — Existing draft ID from server, or null
 * @param {object}  [params.options]
 * @param {number}  [params.options.debounceMs=2000] — Debounce delay in ms
 * @returns {{ autoSaveStatus, draftId, setDraftId }}
 */
export default function useAutoSave({ formData, draftId, options = {} }) {
    const { debounceMs = 2000 } = options;
    const [autoSaveStatus, setAutoSaveStatus] = useState('idle'); // idle | saving | saved | error
    const [localDraftId, setLocalDraftId] = useState(null);
    const effectiveDraftId = draftId || localDraftId;

    // --- Refs ---
    const isFirstRenderRef = useRef(true);
    const debounceRef = useRef(null);
    const abortRef = useRef(null);
    const lastSavedDataRef = useRef('');

    // Skip first render — set to false after mount
    useEffect(() => {
        isFirstRenderRef.current = false;
    }, []);

    // --- doSave: the core save function ---
    const doSave = useCallback(async (data, isStepSave = false) => {
        if (!effectiveDraftId) return null;

        // Abort any in-flight request
        if (abortRef.current) {
            abortRef.current.abort();
        }
        abortRef.current = new AbortController();

        setAutoSaveStatus('saving');

        // Strip UI-only fields before sending
        const payload = { ...data };
        delete payload.has_next_of_kin;

        try {
            const response = await fetch(`/cases/${effectiveDraftId}/save-draft`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ ...payload, is_draft: true }),
                signal: abortRef.current.signal,
            });

            if (!response.ok) {
                throw new Error(`Save failed: ${response.status}`);
            }

            const result = await response.json();

            // Remember what we saved so we can skip duplicate saves
            lastSavedDataRef.current = JSON.stringify(data);
            setAutoSaveStatus('saved');

            // Clear 'saved' status back to 'idle' after 2 seconds
            setTimeout(() => {
                setAutoSaveStatus((prev) => (prev === 'saved' ? 'idle' : prev));
            }, 2000);

            return result;
        } catch (err) {
            if (err.name === 'AbortError') {
                // Silently ignore aborted requests — a newer save is in flight
                return null;
            }
            setAutoSaveStatus('error');
            console.error('Auto-save failed:', err);
            return null;
        }
    }, [effectiveDraftId]);

    // --- Debounced field-change auto-save ---
    useEffect(() => {
        // Skip the very first render
        if (isFirstRenderRef.current) return;

        // Skip if data hasn't actually changed from last saved snapshot
        const currentSerialized = JSON.stringify(formData);
        if (currentSerialized === lastSavedDataRef.current) return;

        // Clear any pending debounce
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        // Set a new debounce timer
        debounceRef.current = setTimeout(() => {
            if (effectiveDraftId) {
                doSave(formData);
            }
        }, debounceMs);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [formData, effectiveDraftId, debounceMs, doSave]);

    const cancelPendingSave = useCallback(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }
        if (abortRef.current) {
            abortRef.current.abort();
            abortRef.current = null;
        }
    }, []);

    // --- Page-unload auto-save via sendBeacon ---
    useEffect(() => {
        const handler = () => {
            if (!effectiveDraftId) return;

            // Strip UI-only fields
            const payload = { ...formData };
            delete payload.has_next_of_kin;

            const blob = new Blob(
                [JSON.stringify({ ...payload, is_draft: true })],
                { type: 'application/json' },
            );
            navigator.sendBeacon(`/cases/${effectiveDraftId}/save-draft`, blob);
        };

        window.addEventListener('beforeunload', handler);
        return () => window.removeEventListener('beforeunload', handler);
    }, [effectiveDraftId, formData]);

    return {
        autoSaveStatus,
        draftId: effectiveDraftId,
        setDraftId: setLocalDraftId,
        cancelPendingSave,
    };
}
