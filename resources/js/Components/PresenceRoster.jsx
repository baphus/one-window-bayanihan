import useLiveViewers from '@/Hooks/useLiveViewers';

function initials(name) {
    return String(name ?? '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || '?';
}

export default function PresenceRoster({ url, enabled = true, currentUserId }) {
    const viewers = useLiveViewers(url, enabled);
    if (!viewers.length) return null;

    return (
        <div className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1.5" aria-label="Currently viewing">
            <span className="whitespace-nowrap text-[10px] font-semibold text-slate-500">Currently viewing</span>
            <ul className="flex -space-x-2" aria-label="People currently viewing">
                {viewers.map((viewer) => {
                    const isSelf = currentUserId != null && String(viewer.id) === String(currentUserId);
                    const label = isSelf ? 'You' : viewer.name;
                    return (
                        <li key={viewer.id} className="flex" aria-label={label}>
                            <span title={label} aria-label={label} className="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-[#233f82] text-[9px] font-bold text-white">
                                <span aria-hidden="true">{initials(viewer.name)}</span>
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
