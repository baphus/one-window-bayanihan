import { useEffect, useRef, useState, useCallback } from 'react';

/**
 * useAutoSave — debounced field-change auto-save + step-navigation save + page-unload save
 * for the case creation draft system.
 *
 * @param {object}  params
 * @param {object}  params.formData  — The current form data (deeply nested object)
 * @param {string|null} params.draftId — Existing draft ID from server, or null
 * @param {number}  params.step     — Current wizard step (1–3)
 * @param {object}  [params.options]
 * @param {number}  [params.options.debounceMs=2000] — Debounce delay in ms
 * @returns {{ autoSaveStatus, saveOnStepChange, draftId, setDraftId }}
 */
export default function useAutoSave({ formData, draftId, step, options = {} }) {
    const { debounceMs = 2000 } = options;
    const [autoSaveStatus, setAutoSaveStatus] = useState('idle'); // idle | saving | saved | error
    const [localDraftId, setLocalDraftId] = useState(null);
    const effectiveDraftId = draftId || localDraftId;

    // --- Refs ---
    const isFirstRenderRef = useRef(true);
    const debounceRef = useRef(null);
    const abortRef = useRef(null);
    const lastSavedDataRef = useRef('');

    // Track step changes to trigger immediate saves
    const lastStepRef = useRef(step);

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

    // --- Step-navigation save ---
    // Detect step changes and save immediately when navigating forward
    useEffect(() => {
        if (isFirstRenderRef.current) return;

        const prevStep = lastStepRef.current;
        lastStepRef.current = step;

        // Only save on forward navigation (step increased)
        if (step > prevStep) {
            // Cancel any pending debounce
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            // Check if there's meaningful data to save
            const hasData =
                formData.client?.first_name?.trim()?.length > 0 ||
                formData.client?.last_name?.trim()?.length > 0;

            if (hasData && effectiveDraftId) {
                doSave(formData, true);
            }
        }
    }, [step, formData, effectiveDraftId, doSave]);

    // --- saveOnStepChange: exposed function for manual step navigation ---
    const saveOnStepChange = useCallback(
        async (currentStep, nextStep) => {
            // Cancel any pending debounce
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            // Do NOT save if no meaningful data entered
            const hasData =
                formData.client?.first_name?.trim()?.length > 0 ||
                formData.client?.last_name?.trim()?.length > 0;
            if (!hasData) return null;

            // Save immediately (not debounced)
            const result = await doSave(formData, true);
            return result?.id || null;
        },
        [formData, doSave],
    );

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
        saveOnStepChange,
        draftId: effectiveDraftId,
        setDraftId: setLocalDraftId,
    };
}
