import { Eye } from 'lucide-react';
import { actionConfig } from './activityConfig';

export default function ActivityItem({ title, desc, time, logoSrc, actionType, actor, message, detail }) {
    const cfg = actionConfig[actionType] || { icon: Eye, bg: 'bg-slate-50', text: 'text-slate-500', ring: 'ring-slate-200', label: '' };
    const ActionIcon = cfg.icon;
    const displayTitle = message ?? title;
    const displayDesc = title === desc ? '' : (detail || desc);

    return (
        <div className="relative flex items-start gap-3">
            {actionType ? (
                <span className={`shrink-0 flex items-center justify-center w-7 h-7 rounded-full ring-2 ring-white ${cfg.bg} shadow-sm`}>
                    <ActionIcon className={`w-3.5 h-3.5 ${cfg.text}`} />
                </span>
            ) : (
                <span className="shrink-0 absolute -left-[25px] top-0 h-4 w-4 overflow-hidden rounded-full border border-white bg-white shadow-sm">
                    <img src={logoSrc} alt="Activity source" className="h-full w-full object-contain p-[1px]" />
                </span>
            )}
            <div className="space-y-0.5 min-w-0">
                <p className="text-xs font-bold text-slate-900 font-body leading-snug">{displayTitle}</p>
                {displayDesc && (
                    <p className="text-[11px] text-slate-500 font-body leading-relaxed line-clamp-2">{displayDesc}</p>
                )}
                <p className="text-[9px] font-bold uppercase tracking-widest text-blue-800">{time}</p>
            </div>
        </div>
    );
}
