import { useEffect, useRef, useState, useCallback } from 'react';

/**
 * useAutoSave — revision-aware server auto-save for case drafts.
 *
 * Creates new drafts via POST /case-drafts on first save, then updates via
 * PUT /case-drafts/{draft} with expected_revision for optimistic concurrency.
 * Exposes `flush()` for explicit synchronous saves (e.g. before publish).
 *
 * @param {object}  params
 * @param {object}  params.formData            — The current form data
 * @param {string|null} params.draftId         — Existing draft ID from server, or null
 * @param {number}  [params.initialRevision=1] — Starting revision for new drafts
 * @param {object}  [params.options]
 * @param {number}  [params.options.debounceMs=2000] — Debounce delay in ms
 * @returns {{ autoSaveStatus, draftId, revision, flush, cancelPendingSave }}
 */
export default function useAutoSave({ formData, draftId, initialRevision = 1, options = {} }) {
    const { debounceMs = 2000 } = options;
    const [autoSaveStatus, setAutoSaveStatus] = useState('idle');
    const [localDraftId, setLocalDraftId] = useState(null);
    const [revision, setRevision] = useState(initialRevision);
    const effectiveDraftId = draftId || localDraftId;

    // --- Refs (avoids stale closures in async callbacks) ---
    const isFirstRenderRef = useRef(true);
    const debounceRef = useRef(null);
    const abortRef = useRef(null);
    const lastSavedDataRef = useRef('');
    const draftIdRef = useRef(null);
    const revisionRef = useRef(initialRevision);

    // Sync refs with state so async doSave always reads the latest value
    useEffect(() => {
        draftIdRef.current = effectiveDraftId;
    }, [effectiveDraftId]);

    useEffect(() => {
        revisionRef.current = revision;
    }, [revision]);

    // Skip the very first render
    useEffect(() => {
        isFirstRenderRef.current = false;
    }, []);

    // --- Strip UI-only fields that the server doesn't understand ---
    const stripUIFields = useCallback((data) => {
        const payload = { ...data };
        delete payload.has_next_of_kin;
        delete payload.selected_nok_index;
        delete payload.is_draft;
        return payload;
    }, []);

    // --- doSave: core save function (create or update) ---
    const doSave = useCallback(async (data) => {
        const currentDraftId = draftIdRef.current;
        const currentRevision = revisionRef.current;

        // Abort any in-flight request before starting a new one
        if (abortRef.current) {
            abortRef.current.abort();
        }
        abortRef.current = new AbortController();

        setAutoSaveStatus('saving');

        try {
            const isCreate = !currentDraftId;
            const url = isCreate ? '/case-drafts' : `/case-drafts/${currentDraftId}`;
            const method = isCreate ? 'POST' : 'PUT';

            const payload = stripUIFields(data);

            // Updates carry the expected revision for optimistic concurrency
            if (!isCreate) {
                payload.expected_revision = currentRevision;
            }

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload),
                signal: abortRef.current.signal,
                credentials: 'same-origin',
            });

            // 409 Conflict — stale revision, component should handle display
            if (response.status === 409) {
                setAutoSaveStatus('conflict');
                return { ok: false, status: 'conflict' };
            }

            if (!response.ok) {
                throw new Error(`Save failed: ${response.status}`);
            }

            const result = await response.json();

            // On first creation, persist the server-assigned draft ID
            if (isCreate && result.id) {
                setLocalDraftId(result.id);
                draftIdRef.current = result.id;
            }

            // Bump revision from server response
            if (result.revision !== undefined) {
                setRevision(result.revision);
                revisionRef.current = result.revision;
            }

            // Remember the serialized data so we can skip duplicate saves
            lastSavedDataRef.current = JSON.stringify(data);
            setAutoSaveStatus('saved');

            // Clear 'saved' status back to 'idle' after 2 seconds
            setTimeout(() => {
                setAutoSaveStatus((prev) => (prev === 'saved' ? 'idle' : prev));
            }, 2000);

            return { ok: true, result };
        } catch (err) {
            if (err.name === 'AbortError') {
                // Silently ignore — a newer save is already in flight
                return { ok: false, aborted: true };
            }
            setAutoSaveStatus('error');
            console.error('Auto-save failed:', err);
            return { ok: false, error: err };
        }
    }, [stripUIFields]);

    // --- Debounced auto-save on form data changes ---
    useEffect(() => {
        if (isFirstRenderRef.current) return;

        // Skip if data hasn't changed since the last successful save
        const currentSerialized = JSON.stringify(formData);
        if (currentSerialized === lastSavedDataRef.current) return;

        // Clear any pending debounce
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        // Schedule the save
        debounceRef.current = setTimeout(() => {
            doSave(formData);
        }, debounceMs);

        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, [formData, debounceMs, doSave]);

    // --- cancelPendingSave: cancel debounced and in-flight saves ---
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

    // --- flush: resolve all pending saves synchronously, return a promise ---
    const flush = useCallback(async () => {
        // Cancel any pending debounce
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }

        // Abort any in-flight request so we start fresh
        if (abortRef.current) {
            abortRef.current.abort();
            abortRef.current = null;
        }

        // Save immediately
        const outcome = await doSave(formData);

        // Reject on actual errors (not aborts, not 409 conflicts)
        if (!outcome.ok && outcome.error) {
            throw outcome.error;
        }

        return outcome.result;
    }, [formData, doSave]);

    return {
        autoSaveStatus,
        draftId: effectiveDraftId,
        revision,
        flush,
        cancelPendingSave,
    };
}
