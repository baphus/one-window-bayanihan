import { useState, useEffect, useRef, useCallback } from 'react';
import Modal from '@/Components/Modal';
import { getAvatarColor } from '@/Components/ui/UserAvatar';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** First+last initials, e.g. "Juan Dela Cruz" → "JC" */
function getInitials(firstName, lastName) {
    const a = firstName?.[0] || '';
    const b = lastName?.[0] || '';
    return (a + b).toUpperCase() || '?';
}

/** Compute age from ISO date string, return string like "32 yrs" or empty */
function formatAge(dob) {
    if (!dob) return '';
    const birth = new Date(dob);
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const m = now.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) age--;
    return age >= 0 ? `${age} yrs` : '';
}

// ── Client card ──────────────────────────────────────────────────────────────

function ClientCard({ client, onSelect }) {
    const fullName = [client.first_name, client.middle_initial, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    const initials = getInitials(client.first_name, client.last_name);
    const avatarBg = getAvatarColor(fullName);
    const age = formatAge(client.date_of_birth);

    return (
        <button
            type="button"
            onClick={() => onSelect(client)}
            aria-label={`Select client ${fullName}`}
            className="group flex w-full items-center gap-3.5 rounded-xl border border-slate-200/80 bg-white px-4 py-3 text-left transition-all duration-150 hover:border-primary/30 hover:bg-primary/[0.02] hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:ring-offset-1"
        >
            {/* ── Circular avatar ── */}
            <span className="relative h-11 w-11 shrink-0 rounded-circle overflow-hidden flex-shrink-0">
                {client.avatar_url ? (
                    <img
                        src={client.avatar_url}
                        alt={fullName}
                        className="absolute inset-0 h-full w-full rounded-circle object-cover border border-slate-200"
                        onError={(e) => {
                            e.target.style.display = 'none';
                            e.target.parentElement.querySelector('.avatar-fallback')?.classList.remove('hidden');
                        }}
                    />
                ) : null}
                <span
                    className={`avatar-fallback ${client.avatar_url ? 'hidden' : ''} absolute inset-0 h-full w-full rounded-circle flex items-center justify-center text-white font-semibold text-[13px] select-none ${avatarBg}`}
                    aria-hidden="true"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="absolute w-3/5 h-3/5 text-white/20">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span className="relative z-10">{initials}</span>
                </span>
            </span>

            {/* ── Info ── */}
            <div className="min-w-0 flex-1">
                <p className="text-[13.5px] font-semibold text-slate-900 truncate leading-snug group-hover:text-primary transition-colors duration-150">
                    {fullName}
                </p>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11.5px] text-slate-500 leading-none">
                    {client.case_file?.case_number && (
                        <span className="inline-flex items-center gap-1">
                            <span className="material-symbols-outlined text-[13px] leading-none">folder</span>
                            <span className="font-medium text-slate-600">{client.case_file.case_number}</span>
                        </span>
                    )}
                    {client.sex && (
                        <span>{client.sex}</span>
                    )}
                    {age && (
                        <span>{age}</span>
                    )}
                </div>
            </div>

            {/* ── Chevron ── */}
            <span className="material-symbols-outlined text-[18px] text-slate-300 opacity-0 transition-all duration-150 group-hover:opacity-100 group-hover:text-primary/60 shrink-0 -ml-1">
                chevron_right
            </span>
        </button>
    );
}

// ── Main modal ───────────────────────────────────────────────────────────────

export default function ExistingClientModal({ show, onClose, onSelect }) {
    const [query, setQuery] = useState('');
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(false);
    const searchInputRef = useRef(null);
    const debounceTimer = useRef(null);

    // Auto-focus search input when modal opens
    useEffect(() => {
        if (show) {
            setQuery('');
            setClients([]);
            setTimeout(() => searchInputRef.current?.focus(), 100);
        }
    }, [show]);

    // Debounced search
    const performSearch = useCallback((searchTerm) => {
        setLoading(true);
        const url = searchTerm.trim()
            ? `/api/clients?q=${encodeURIComponent(searchTerm.trim())}`
            : '/api/clients';

        axios.get(url)
            .then((res) => {
                setClients(res.data.data || []);
            })
            .catch(() => {
                setClients([]);
            })
            .finally(() => setLoading(false));
    }, []);

    const handleSearchChange = useCallback((e) => {
        const value = e.target.value;
        setQuery(value);

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => {
            performSearch(value);
        }, 300);
    }, [performSearch]);

    const handleClearSearch = useCallback(() => {
        setQuery('');
        searchInputRef.current?.focus();
        if (debounceTimer.current) clearTimeout(debounceTimer.current);
        performSearch('');
    }, [performSearch]);

    // Initial load when modal opens
    useEffect(() => {
        if (show) {
            performSearch('');
        }
    }, [show, performSearch]);

    const handleSelect = (client) => {
        onSelect(client);
        onClose();
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <div className="flex flex-col">
                {/* ── Header ── */}
                <div className="flex items-center justify-between px-6 pt-6 pb-0">
                    <div>
                        <h2 className="text-[15px] font-bold text-slate-900 tracking-tight">
                            Select Existing Client
                        </h2>
                        <p className="text-[12px] text-slate-500 mt-0.5">
                            Search by name or browse recent clients below.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40"
                        aria-label="Close"
                    >
                        <span className="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {/* ── Search ── */}
                <div className="relative px-6 pt-4 pb-3">
                    <span className="absolute left-[2.25rem] top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                        <span className="material-symbols-outlined text-[18px]">search</span>
                    </span>
                    <input
                        ref={searchInputRef}
                        type="text"
                        value={query}
                        onChange={handleSearchChange}
                        placeholder="Search clients by name..."
                        aria-label="Search clients"
                        className="h-10 w-full rounded-lg border border-slate-200 bg-slate-50/60 pl-10 pr-9 text-[13px] text-slate-700 placeholder:text-slate-400 outline-none transition-colors focus:border-primary/40 focus:bg-white focus:ring-1 focus:ring-primary/20"
                    />
                    {query && (
                        <button
                            type="button"
                            onClick={handleClearSearch}
                            className="absolute right-[1.75rem] top-1/2 -translate-y-1/2 rounded-full p-0.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-600 transition-colors focus:outline-none focus:ring-1 focus:ring-primary/30"
                            aria-label="Clear search"
                        >
                            <span className="material-symbols-outlined text-[16px]">close</span>
                        </button>
                    )}
                </div>

                {/* ── Divider ── */}
                <div className="border-t border-slate-100 mx-6" />

                {/* ── Results ── */}
                <div className="px-6 pb-5 pt-3">
                    {loading ? (
                        <div className="flex items-center justify-center py-14">
                            <div className="flex items-center gap-2.5 text-slate-400">
                                <span className="material-symbols-outlined text-[20px] animate-spin">progress_activity</span>
                                <span className="text-[13px]">Searching clients...</span>
                            </div>
                        </div>
                    ) : clients.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 text-slate-400">
                            <span className="material-symbols-outlined text-[36px] mb-2 text-slate-300">person_search</span>
                            <p className="text-[13px] font-medium text-slate-500">No clients found</p>
                            <p className="text-[12px] text-slate-400 mt-0.5">
                                Try a different search term, or create a new client instead.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Result count */}
                            <p className="mb-2.5 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                {clients.length} client{clients.length !== 1 ? 's' : ''}
                            </p>

                            {/* Card grid */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[400px] overflow-y-auto pr-1">
                                {clients.map((client) => (
                                    <ClientCard
                                        key={client.id}
                                        client={client}
                                        onSelect={handleSelect}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </Modal>
    );
}
