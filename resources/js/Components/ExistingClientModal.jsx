import { useState, useEffect, useRef, useCallback } from 'react';
import Modal from '@/Components/Modal';

function getInitial(name) {
    if (!name) return '?';
    return name.charAt(0).toUpperCase();
}

function ClientCard({ client, onSelect }) {
    const fullName = [client.first_name, client.middle_initial, client.last_name, client.suffix]
        .filter(Boolean)
        .join(' ');

    return (
        <button
            type="button"
            onClick={() => onSelect(client)}
            className="flex w-full items-start gap-4 rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm transition-all hover:border-indigo-400 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
            {/* Avatar */}
            <div className="h-12 w-12 shrink-0 rounded-circle overflow-hidden flex items-center justify-center bg-indigo-100 relative">
                {client.avatar_url ? (
                    <img
                        src={client.avatar_url}
                        alt={fullName}
                        className="h-full w-full object-cover"
                        onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.querySelector('.avatar-fallback').classList.remove('hidden'); }}
                    />
                ) : null}
                <span className={`${client.avatar_url ? 'avatar-fallback hidden' : ''} absolute inset-0 flex items-center justify-center bg-indigo-100 rounded-circle`}>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="absolute w-3/5 h-3/5 text-indigo-300">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span className="relative z-10 text-sm font-semibold text-indigo-700 select-none">
                        {getInitial(client.first_name)}
                    </span>
                </span>
            </div>

            {/* Details */}
            <div className="min-w-0 flex-1">
                <p className="text-[14px] font-bold text-slate-900 truncate">{fullName}</p>
                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-slate-500">
                    {client.case_file?.case_number && (
                        <span>Case: <span className="font-medium text-slate-700">{client.case_file.case_number}</span></span>
                    )}
                    {client.sex && (
                        <span>Sex: <span className="font-medium text-slate-700">{client.sex}</span></span>
                    )}
                    {client.date_of_birth && (
                        <span>DOB: <span className="font-medium text-slate-700">{client.date_of_birth}</span></span>
                    )}
                </div>
            </div>
        </button>
    );
}

export default function ExistingClientModal({ show, onClose, onSelect }) {
    const [query, setQuery] = useState('');
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(false);
    const searchInputRef = useRef(null);
    const debounceTimer = useRef(null);

    // Auto-focus search input when modal opens
    useEffect(() => {
        if (show) {
            // Reset state when modal opens
            setQuery('');
            setClients([]);
            // Small delay to let modal render
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

    // Initial load when modal opens (empty search = recent 20)
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
        <Modal show={show} onClose={onClose} maxWidth="4xl">
            <div className="p-6">
                {/* Header */}
                <div className="mb-5 flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900">Select Existing Client</h2>
                        <p className="text-[13px] text-slate-500 mt-1">Search or browse to select a client for this case.</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    >
                        <span className="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {/* Search input */}
                <div className="relative mb-5">
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <span className="material-symbols-outlined text-[18px]">search</span>
                    </span>
                    <input
                        ref={searchInputRef}
                        type="text"
                        value={query}
                        onChange={handleSearchChange}
                        placeholder="Search by client name..."
                        className="h-11 w-full rounded-lg border border-slate-300 pl-10 pr-4 text-[13px] text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                {/* Results */}
                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="flex items-center gap-3 text-slate-500">
                            <svg className="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            <span className="text-[13px]">Searching clients...</span>
                        </div>
                    </div>
                ) : clients.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-slate-400">
                        <span className="material-symbols-outlined text-[40px] mb-3">person_search</span>
                        <p className="text-[14px] font-medium text-slate-500">No clients found</p>
                        <p className="text-[12px] text-slate-400 mt-1">Try a different search term or create a new client.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[420px] overflow-y-auto pr-1">
                        {clients.map((client) => (
                            <ClientCard
                                key={client.id}
                                client={client}
                                onSelect={handleSelect}
                            />
                        ))}
                    </div>
                )}
            </div>
        </Modal>
    );
}
