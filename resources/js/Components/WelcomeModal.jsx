import Modal from '@/Components/Modal';

export default function WelcomeModal({
    show = true,
    onStartTour,
    onSkipTour,
    onRemindLater,
}) {
    return (
        <Modal show={show} maxWidth="md" onClose={onRemindLater}>
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
            <div className="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                <button
                    onClick={onRemindLater}
                    className="h-9 px-2 text-[12px] font-bold text-slate-500 hover:text-slate-700 transition-colors"
                >
                    Remind Me Later
                </button>
                <button
                    onClick={onSkipTour}
                    className="h-9 rounded-[3px] border border-slate-300 px-4 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors"
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
        </Modal>
    );
}
