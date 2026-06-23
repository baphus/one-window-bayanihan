export default function WelcomeModal({
    onStartTour,
    onSkipTour,
    onRemindLater,
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <div className="w-full max-w-md rounded-[3px] border-t-4 border-indigo-500 bg-white shadow-xl">
                <div className="px-6 py-5 text-center">
                    <span
                        className="material-symbols-outlined text-[40px] text-indigo-600"
                        style={{ fontVariationSettings: "'FILL' 1, 'wght' 400" }}
                    >
                        travel_explore
                    </span>
                    <h2 className="mt-3 text-[16px] font-extrabold text-slate-900">
                        Welcome to One Window Bayanihan!
                    </h2>
                    <p className="mt-1 text-[13px] font-semibold text-slate-600">
                        Let&apos;s take a quick tour to help you get started.
                    </p>
                    <p className="mt-2 text-[13px] text-slate-600 leading-relaxed">
                        Discover how to manage cases, track referrals, and
                        collaborate with agencies &mdash; all in one place.
                    </p>
                </div>
                <div className="flex items-center justify-end gap-3 border-t border-[#e2e8f0] px-6 py-4">
                    <button
                        onClick={onRemindLater}
                        className="h-9 px-2 text-[12px] font-bold text-slate-500 hover:text-slate-700 transition-colors"
                    >
                        Remind Me Later
                    </button>
                    <button
                        onClick={onSkipTour}
                        className="h-9 rounded-[3px] border border-[#cbd5e1] px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors"
                    >
                        Skip Tour
                    </button>
                    <button
                        onClick={onStartTour}
                        className="h-9 rounded-[3px] bg-indigo-600 px-4 text-[12px] font-bold text-white hover:bg-indigo-700 transition-colors"
                    >
                        Start Tour
                    </button>
                </div>
            </div>
        </div>
    );
}
