const dotColors = {
    PENDING: 'bg-yellow-500',
    PROCESSING: 'bg-blue-500',
    FOR_COMPLIANCE: 'bg-orange-500',
    COMPLETED: 'bg-green-500',
    REJECTED: 'bg-red-500',
    default: 'bg-indigo-500',
};

const lineColors = {
    PENDING: 'border-yellow-300',
    PROCESSING: 'border-blue-300',
    FOR_COMPLIANCE: 'border-orange-300',
    COMPLETED: 'border-green-300',
    REJECTED: 'border-red-300',
    default: 'border-indigo-300',
};

export default function Timeline({ items, dotColorKey = 'default' }) {
    if (!items || items.length === 0) {
        return <p className="text-sm text-slate-500 py-4 text-center">No timeline events recorded.</p>;
    }

    return (
        <div className="relative">
            {items.map((item, idx) => {
                const dotColor = dotColors[item[dotColorKey]] || dotColors.default;
                const lineColor = lineColors[item[dotColorKey]] || lineColors.default;
                const isLast = idx === items.length - 1;

                return (
                    <div key={item.id || idx} className="relative flex gap-4 pb-6 last:pb-0">
                        <div className="flex flex-col items-center">
                            <div className={`h-3 w-3 rounded-full ${dotColor} ring-2 ring-white z-10 shrink-0`} />
                            {!isLast && (
                                <div className={`w-0 flex-1 border-l-2 ${lineColor} mt-1`} />
                            )}
                        </div>
                        <div className="flex-1 min-w-0 -mt-0.5">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="text-sm font-semibold text-slate-900">{item.title}</p>
                                    {item.description && (
                                        <p className="text-xs text-slate-500 mt-0.5">{item.description}</p>
                                    )}
                                </div>
                                <span className="text-xs text-slate-400 whitespace-nowrap">
                                    {item.date ? new Date(item.date).toLocaleDateString() : ''}
                                </span>
                            </div>
                            {item.meta && (
                                <p className="text-xs text-slate-400 mt-1">{item.meta}</p>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
