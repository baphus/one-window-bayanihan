import { useState } from 'react';
import axios from 'axios';
import { Sparkles, Loader2, AlertCircle, X } from 'lucide-react';

export default function AiInsightPanel({ fromDate, toDate }) {
  const [insight, setInsight] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);

  const generateInsight = async () => {
    setLoading(true);
    setError(null);
    setInsight(null);
    setOpen(true);

    try {
      const params = {};
      if (fromDate) params.from = fromDate;
      if (toDate) params.to = toDate;

      const res = await axios.post(route('reports.ai-insight'), params);
      if (res.data.insight) {
        setInsight(res.data.insight);
      } else {
        setError(res.data.error || 'AI returned no insight.');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Failed to generate AI insight. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const close = () => {
    setOpen(false);
    // Don't clear content immediately to allow slide-out animation
    setTimeout(() => { setInsight(null); setError(null); }, 300);
  };

  return (
    <>
      {/* Trigger button — always visible in the page header area */}
      <button
        type="button"
        onClick={generateInsight}
        disabled={loading}
        className="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-[12px] font-bold text-indigo-700 transition-colors hover:bg-indigo-100 disabled:opacity-50"
      >
        <Sparkles className="h-4 w-4" />
        {loading ? 'Analyzing...' : 'AI Insights'}
      </button>

      {/* Overlay backdrop */}
      {open && (
        <div
          className="fixed inset-0 z-40 bg-black/20 transition-opacity duration-300"
          onClick={close}
        />
      )}

      {/* Slide-out panel */}
      <div
        className={`fixed inset-y-0 right-0 z-50 w-96 max-w-full bg-white shadow-xl transform transition-transform duration-300 ease-in-out ${
          open ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {/* Header */}
        <div className="flex items-center justify-between border-b border-indigo-100 px-4 py-3">
          <div className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-indigo-600" />
            <h3 className="text-[11px] font-bold uppercase tracking-[0.14em] text-indigo-700">
              AI Insight
            </h3>
          </div>
          <button
            type="button"
            onClick={close}
            className="rounded p-1 text-slate-400 hover:bg-indigo-100 hover:text-slate-600"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        {/* Content */}
        <div className="overflow-y-auto px-4 py-3" style={{ maxHeight: 'calc(100vh - 60px)' }}>
          {loading ? (
            <div className="flex items-center gap-3 py-6">
              <Loader2 className="h-5 w-5 animate-spin text-indigo-500" />
              <span className="text-[12px] text-slate-500">Analyzing report data...</span>
            </div>
          ) : error ? (
            <div className="flex items-start gap-2">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
              <p className="text-[12px] leading-relaxed text-slate-600">{error}</p>
            </div>
          ) : insight ? (
            <div className="space-y-2">
              {insight.split('\n').filter(Boolean).map((paragraph, i) => (
                <p key={i} className="text-[12px] leading-relaxed text-slate-700">
                  {paragraph}
                </p>
              ))}
            </div>
          ) : null}

          {!loading && (
            <button
              type="button"
              onClick={generateInsight}
              className="mt-3 inline-flex items-center gap-1.5 text-[10px] font-bold text-indigo-600 hover:text-indigo-800"
            >
              <Sparkles className="h-3 w-3" />
              Regenerate
            </button>
          )}
        </div>
      </div>
    </>
  );
}
