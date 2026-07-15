import { usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import axios from 'axios';
import { MessageCircle, X, Send } from 'lucide-react';
import TurnstileWidget from '@/Components/TurnstileWidget';

const CHAT_HISTORY_KEY = 'owb_chat_history';

function createWelcomeMessage(name) {
    return {
        role: 'bot',
        text: `Hello! I'm **${name}**, your Virtual Bayanihan Assistant for the **One Window Bayanihan** system. How can I help you today? Feel free to ask about services, agencies, case tracking, or any OFW assistance you need.`,
        time: new Date(),
    };
}

const SUGGESTIONS = [
    'Check my case status',
    'OWWA contact number',
    'Lost my tracker number',
    'OTP not working',
    'DMW legal assistance',
];

const SUGGESTION_REPLIES = {
    'Check my case status':
        "To check your case status, you'll need your **Tracker Number** and the **email address** you used when submitting your request. Visit our tracking portal, enter both, and you'll receive a one-time passcode (OTP) via email to verify your identity. Once verified, your case details and current status will be displayed.\n\nIf you don't have your tracker number, check your email confirmation or contact the DMW Region VII office for assistance.",
    'Check my case status|actions': [{ label: 'Go to Tracking Portal', url: '/track', icon: 'track' }],
    'OWWA contact number':
        "**OWWA — Overseas Workers Welfare Administration**\n\n- **Office:** OWWA Regional Welfare Office VII\n- **Hotline:** (02) 8720-1142\n- **Website:** owwa.gov.ph\n- **Services:** Welfare assistance, legal aid, repatriation support, reintegration programs\n\nThey assist with emergency repatriation, welfare support upon arrival, and family assistance programs for OFWs.",
    'Lost my tracker number':
        "If you've lost your tracker number:\n\n1. **Check your email inbox** for the automated confirmation message you received when you submitted your request. The subject line will mention \"One Window Bayanihan — Case Confirmation\".\n2. If you have a printed **acknowledgment receipt**, the tracker number is printed there.\n3. If neither works, **contact the DMW Region VII office** directly. Provide your full name, date of submission, and the email you used so they can locate your case.",
    'OTP not working':
        "Here are some tips if your OTP isn't working:\n\n1. **Check your Spam/Junk folder** — automated emails sometimes end up there.\n2. **Wait 1-2 minutes** — delivery delays happen occasionally.\n3. **Click \"Resend OTP\"** — you can request a new code anytime. The previous one will expire immediately.\n4. **OTPs expire after 5 minutes** — if yours expired, just request a new one.\n5. **Double-check your email** — look for typos like \"gmial.com\" instead of \"gmail.com\".\n\nIf it's still not arriving, add the sender domain to your email whitelist and try again.",
    'DMW legal assistance':
        "**DMW — Department of Migrant Workers** provides legal assistance for OFWs including:\n\n- Legal counseling and advice\n- Assistance with employment contract issues\n- Documentation of employment concerns\n- Representation in labor disputes\n- Assistance with illegal recruitment cases\n\n**Contact:** DMW Regional Office VII, Cebu City\n**Hotline:** 1348 (nationwide)\n**Website:** dmw.gov.ph\n\nTo start a case, visit the DMW office or file through the One Window Bayanihan system.",
};

function getProviderLabel(provider) {
    const labels = {
        openai: 'OpenAI',
        anthropic: 'Claude (Anthropic)',
        gemini: 'Google Gemini',
        ollama: 'Ollama',
        azure: 'Azure OpenAI',
        groq: 'Groq',
        mistral: 'Mistral',
        deepseek: 'DeepSeek',
    };
    return labels[provider] || provider || 'AI';
}

function loadChatHistory() {
    try {
        const saved = localStorage.getItem(CHAT_HISTORY_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.map((m) => ({
                    ...m,
                    time: m.time ? new Date(m.time) : new Date(),
                }));
            }
        }
    } catch {
        /* localStorage unavailable */
    }
    return null;
}

function AssistantAvatar({ size = 32 }) {
    const s = size;
    return (
        <svg
            width={s}
            height={s}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className="shrink-0"
        >
            {[0, 45, 90, 135, 180, 225, 270, 315].map((angle) => (
                <line
                    key={angle}
                    x1={16 + 8 * Math.cos((angle * Math.PI) / 180)}
                    y1={16 + 8 * Math.sin((angle * Math.PI) / 180)}
                    x2={16 + 11 * Math.cos((angle * Math.PI) / 180)}
                    y2={16 + 11 * Math.sin((angle * Math.PI) / 180)}
                    stroke="#FCD34D"
                    strokeWidth="1.2"
                    strokeLinecap="round"
                />
            ))}
            <circle cx="16" cy="16" r="10" fill="#FDE68A" stroke="#F59E0B" strokeWidth="0.5" />
            <path d="M11 12Q16 8 21 12" stroke="#92400E" strokeWidth="1.5" fill="none" strokeLinecap="round" />
            <circle cx="12.5" cy="15.5" r="1.2" fill="#92400E" />
            <circle cx="19.5" cy="15.5" r="1.2" fill="#92400E" />
            <path d="M11.5 18.5Q16 22 20.5 18.5" stroke="#92400E" strokeWidth="1.2" fill="none" strokeLinecap="round" />
            <circle cx="10.5" cy="17.5" r="1.5" fill="#FCA5A5" opacity="0.4" />
            <circle cx="21.5" cy="17.5" r="1.5" fill="#FCA5A5" opacity="0.4" />
        </svg>
    );
}

function LoadingMessages({ name }) {
    const messages = [
        `${name} is thinking...`,
        `${name} is working on your answer...`,
        `${name} is looking into that...`,
        `${name} is putting everything together...`,
        `${name} is almost ready...`,
    ];
    const [index, setIndex] = useState(0);

    useEffect(() => {
        const timer = setInterval(() => {
            setIndex((_) => Math.floor(Math.random() * messages.length));
        }, 3000);

        return () => clearInterval(timer);
    }, [messages.length]);

    return (
        <div className="flex items-center px-1 text-sm text-on-surface-variant">
            <span key={index} className="inline-flex flex-wrap">
                {[...messages[index]].map((char, i) => (
                    <span
                        key={i}
                        className="inline-block animate-bounce"
                        style={{ animationDelay: `${i * 40}ms`, animationDuration: '1s' }}
                    >
                        {char === ' ' ? '\u00A0' : char}
                    </span>
                ))}
            </span>
        </div>
    );
}

function LinkPreview({ action }) {
    return (
        <button
            type="button"
            onClick={() => router.visit(action.url)}
            className="group w-full text-left transition-all hover:bg-primary/[0.03] active:scale-[0.99]"
        >
            <div className="aspect-[2/1] w-full overflow-hidden bg-gradient-to-br from-primary/5 to-primary-container/20">
                <img
                    src="/images/trackingportal.png"
                    alt=""
                    className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
                    onError={(e) => {
                        e.target.style.display = 'none';
                    }}
                />
            </div>
            <div className="px-4 py-2.5">
                <p className="text-[13px] font-semibold leading-tight text-on-surface group-hover:text-primary transition-colors">
                    {action.label || 'Tracking Portal'}
                </p>
                <p className="mt-0.5 text-[11px] leading-tight text-on-surface-variant/80">
                    Check your case status online — enter your tracker number and OTP to view case details.
                </p>
            </div>
        </button>
    );
}

function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function WelcomeCard({ onSuggestionClick, onClose }) {
    return (
        <div className="relative animate-in fade-in slide-in-from-bottom-2 rounded-none bg-white px-5 py-3 shadow-sm ring-1 ring-outline-variant/60 duration-300">
            <div className="flex items-center justify-between mb-2">
                <p className="text-[11px] font-semibold text-on-surface-variant">Quick help</p>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex h-5 w-5 items-center justify-center rounded text-on-surface-variant/50 transition-all hover:bg-surface-container-low hover:text-on-surface-variant"
                    aria-label="Hide quick help"
                >
                    <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {SUGGESTIONS.map((suggestion) => (
                    <button
                        key={suggestion}
                        type="button"
                        onClick={() => onSuggestionClick(suggestion)}
                        className="rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-medium text-primary transition-all hover:bg-primary/20 hover:shadow-sm active:scale-95"
                    >
                        {suggestion}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function ChatBot() {
    const { chatbot, turnstile } = usePage().props;
    const assistantName = chatbot?.assistant_name || 'Bayani';
    if (!chatbot?.enabled) return null;

    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState(() => {
        const saved = loadChatHistory();
        if (saved) return saved;
        return [];
    });
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(null);
    const [showScrollBtn, setShowScrollBtn] = useState(false);
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [quickHelpVisible, setQuickHelpVisible] = useState(true);
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileVerified, setTurnstileVerified] = useState(false);
    const [showTurnstile, setShowTurnstile] = useState(false);

    const scrollToBottom = useCallback((smooth = true) => {
        if (listRef.current) {
            listRef.current.scrollTo({
                top: listRef.current.scrollHeight,
                behavior: smooth ? 'smooth' : 'instant',
            });
        }
    }, []);

    useEffect(() => {
        if (!loading) scrollToBottom(true);
    }, [messages, loading, scrollToBottom]);

    useEffect(() => {
        setShowClearConfirm(false);
        if (open && inputRef.current) {
            inputRef.current.focus();
        }
        // Show turnstile widget when chat opens if not yet verified
        if (open && turnstile?.enabled && !turnstileVerified) {
            setShowTurnstile(true);
        }
    }, [open]);

    useEffect(() => {
        try {
            if (messages.length === 0) {
                localStorage.removeItem(CHAT_HISTORY_KEY);
            } else {
                const last20 = messages.slice(-20);
                localStorage.setItem(CHAT_HISTORY_KEY, JSON.stringify(last20));
            }
        } catch {
            /* localStorage unavailable */
        }
    }, [messages]);

    const handleScroll = useCallback(() => {
        if (!listRef.current) return;
        const { scrollTop, scrollHeight, clientHeight } = listRef.current;
        setShowScrollBtn(scrollHeight - scrollTop - clientHeight > 120);
    }, []);

    function handleNewSession() {
        if (abortRef.current) {
            abortRef.current.abort();
            abortRef.current = null;
        }
        setLoading(false);
        setMessages([createWelcomeMessage(assistantName)]);
        setShowClearConfirm(false);
        try {
            localStorage.removeItem(CHAT_HISTORY_KEY);
        } catch { /* noop */ }
        if (inputRef.current) inputRef.current.focus();
    }

    function handleSuggestionClick(suggestion) {
        const reply = SUGGESTION_REPLIES[suggestion];
        if (!reply) return;
        const actions = SUGGESTION_REPLIES[suggestion + '|actions'] || [];
        setMessages((prev) => [
            ...prev,
            { role: 'user', text: suggestion, time: new Date() },
            { role: 'bot', text: reply, time: new Date(), actions },
        ]);
        if (inputRef.current) inputRef.current.focus();
    }

    async function handleSend(e, overrideMessage) {
        e?.preventDefault();
        const userMessage = (overrideMessage || input).trim();
        if (!userMessage || loading) return;

        // If turnstile is enabled and not verified, require the token
        const needsTurnstile = turnstile?.enabled && !turnstileVerified;
        if (needsTurnstile && !turnstileToken) {
            setShowTurnstile(true);
            setMessages((prev) => [
                ...prev,
                { role: 'user', text: userMessage, time: new Date() },
                { role: 'bot', text: 'Please complete the verification challenge below before sending messages.', time: new Date() },
            ]);
            return;
        }

        setInput('');
        setMessages((prev) => [
            ...prev,
            { role: 'user', text: userMessage, time: new Date() },
        ]);
        setLoading(true);

        const controller = new AbortController();
        abortRef.current = controller;

        try {
            // Send recent user queries so the backend can detect follow-ups
            const recentHistory = messages
                .filter((msg) => msg.role === 'user')
                .slice(-5)
                .map((msg) => ({
                    role: msg.role,
                    text: msg.text,
                }));
            const payload = {
                message: userMessage,
                history: recentHistory,
            };
            // Include turnstile token on first (unverified) request
            if (needsTurnstile && turnstileToken) {
                payload.cf_turnstile_response = turnstileToken;
            }
            const { data } = await axios.post(route('chatbot.message'), payload, { signal: controller.signal });
            // If we got here with a token, the session is now verified
            if (needsTurnstile) {
                setTurnstileVerified(true);
                setShowTurnstile(false);
            }
            setMessages((prev) => [
                ...prev,
                { role: 'bot', text: data.reply, time: new Date(), actions: data.actions || [] },
            ]);
        } catch (err) {
            if (axios.isCancel(err)) return;
            // Handle turnstile_required error from backend
            if (err.response?.status === 422 && err.response?.data?.error === 'turnstile_required') {
                setShowTurnstile(true);
                setMessages((prev) => [
                    ...prev,
                    { role: 'bot', text: 'Please complete the verification challenge below to continue.', time: new Date() },
                ]);
            } else {
                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'bot',
                        text: 'Sorry, I encountered an error. Please try again.',
                        time: new Date(),
                    },
                ]);
            }
        } finally {
            if (abortRef.current === controller) abortRef.current = null;
            setLoading(false);
        }
    }

    function handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    }

    function renderMessage(msg, i) {
        const isUser = msg.role === 'user';
        return (
            <div
                key={i}
                className={`${i > 0 ? 'mt-4' : ''} animate-in fade-in slide-in-from-bottom-1 duration-300`}
                style={{ animationDelay: `${i * 40}ms`, animationFillMode: 'both' }}
            >
                {isUser ? (
                    <div className="flex flex-col items-end">
                        <div className="max-w-[80%]">
                            <div className="overflow-hidden rounded-xl bg-primary px-4 py-3 text-sm leading-relaxed text-on-primary shadow-md shadow-black/15 [&_p]:text-on-primary [&_strong]:text-on-primary [&_code]:bg-white/20 [&_code]:text-on-primary">
                                <p className="m-0 whitespace-pre-wrap break-words">{msg.text}</p>
                            </div>
                            <span className="mt-1 block px-1 text-right text-[10px] text-on-surface-variant opacity-0 transition-opacity group-hover:opacity-100">
                                {msg.time ? formatTime(msg.time) : ''}
                            </span>
                        </div>
                    </div>
                ) : (
                    <div className="group flex max-w-[80%] flex-col">
                        <div className="flex gap-3">
                            <div className="self-end shrink-0">
                                <img src="/images/assistantavatar.png" alt={assistantName} className="h-9 w-9 rounded-full" />
                            </div>
                            <div className="overflow-hidden rounded-xl bg-white text-sm leading-relaxed text-on-surface shadow-sm ring-1 ring-outline-variant/60">
                                <div className="chatbot-markdown px-4 py-3">
                                    <ReactMarkdown
                                        remarkPlugins={[remarkGfm]}
                                        components={{
                                            a: ({ href, children }) => (
                                                <a
                                                    href={href}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-primary underline underline-offset-2 hover:text-primary/80"
                                                >
                                                    {children}
                                                </a>
                                            ),
                                            ul: ({ children }) => (
                                                <ul className="my-1 list-disc space-y-0.5 pl-5 marker:text-outline-variant">
                                                    {children}
                                                </ul>
                                            ),
                                            ol: ({ children }) => (
                                                <ol className="my-1 list-decimal space-y-0.5 pl-5 marker:text-outline-variant">
                                                    {children}
                                                </ol>
                                            ),
                                            p: ({ children }) => (
                                                <p className="my-1.5 first:mt-0 last:mb-0 leading-relaxed">
                                                    {children}
                                                </p>
                                            ),
                                            code: ({ className, children, ...props }) => {
                                                const isInline = !className;
                                                return isInline ? (
                                                    <code
                                                        className="rounded bg-slate-200/70 px-1.5 py-0.5 text-xs font-medium text-on-surface"
                                                        {...props}
                                                    >
                                                        {children}
                                                    </code>
                                                ) : (
                                                    <code
                                                        className="block rounded-lg bg-on-surface p-3 text-xs text-inverse-on-surface"
                                                        {...props}
                                                    >
                                                        {children}
                                                    </code>
                                                );
                                            },
                                            strong: ({ children }) => (
                                                <strong className="font-semibold text-on-surface">
                                                    {children}
                                                </strong>
                                            ),
                                            h3: ({ children }) => (
                                                <h3 className="my-2 text-sm font-semibold text-on-surface">
                                                    {children}
                                                </h3>
                                            ),
                                        }}
                                    >
                                        {msg.text}
                                    </ReactMarkdown>
                                </div>
                                {msg.actions && msg.actions.length > 0 && (
                                    <div>
                                        {msg.actions.map((action, ai) => (
                                            action.icon === 'track' ? (
                                                <div key={ai} className="border-t border-outline-variant/30">
                                                    <LinkPreview action={action} />
                                                </div>
                                            ) : (
                                                <div key={ai} className="border-t border-outline-variant/30 px-4 py-2.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => router.visit(action.url)}
                                                        className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-medium text-primary transition-all hover:bg-primary/20 hover:shadow-sm active:scale-95"
                                                    >
                                                        {action.label}
                                                    </button>
                                                </div>
                                            )
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                        <span className="ml-[48px] mt-1 px-1 text-[10px] text-on-surface-variant opacity-0 transition-opacity group-hover:opacity-100">
                            {msg.time ? formatTime(msg.time) : ''}
                        </span>
                    </div>
                )}
            </div>
        );
    }

    return (
        <>
            <style>{`
                @keyframes chat-panel-in {
                    from { opacity: 0; transform: translateY(12px) scale(0.98); }
                    to   { opacity: 1; transform: translateY(0) scale(1); }
                }
                .chat-panel-in {
                    animation: chat-panel-in 0.3s cubic-bezier(0.16, 1, 0.3, 1) both;
                }
                .chat-panel-in::-webkit-resizer {
                    background: linear-gradient(135deg, transparent 35%, rgba(0,0,0,0.08) 35%, rgba(0,0,0,0.08) 45%, transparent 45%, transparent 70%, rgba(0,0,0,0.08) 70%);
                    border-radius: 0 0 0.5rem 0;
                }
            `}</style>

            {/* ── Trigger button ── */}
            {!open && (
                <div data-tour="chatbot-launcher" className="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-2">
                    <div className="animate-in fade-in slide-in-from-bottom-1 rounded-full bg-primary/70 px-4 py-1.5 text-xs font-medium text-on-primary shadow-lg shadow-primary/20 backdrop-blur-sm duration-300">
                        Need help?
                    </div>
                    <button
                        onClick={() => setOpen(true)}
                        className="group flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-lg shadow-primary/30 transition-all duration-200 hover:from-primary hover:to-primary hover:brightness-110 hover:shadow-xl hover:shadow-primary/40 active:scale-95"
                        aria-label="Open chat"
                    >
                        <img src="/images/assistantavatarinverted.png" alt={assistantName} className="h-8 w-8 transition-transform duration-200 group-hover:scale-110" />
                    </button>
                </div>
            )}

            {/* ── Chat panel ── */}
            {open && (
                <>
                    <div
                        className="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm md:hidden"
                        onClick={() => setOpen(false)}
                    />

                    <div className="chat-panel-in fixed bottom-0 right-0 z-50 flex h-full w-full flex-col bg-surface-container-low shadow-2xl shadow-primary/10 md:bottom-5 md:right-5 md:h-[620px] md:w-[420px] md:min-h-[400px] md:min-w-[320px] md:max-h-[90vh] md:max-w-[600px] md:rounded-lg md:shadow-2xl md:backdrop-blur-sm md:resize md:overflow-clip">
                        {/* ── Header ── */}
                        <div className="relative shrink-0 overflow-hidden bg-gradient-to-br from-primary via-primary-container to-primary px-5 pb-5 pt-5 md:rounded-t-lg">
                            <div className="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-primary-fixed-dim/10 blur-2xl" />
                            <div className="pointer-events-none absolute -bottom-6 -left-6 h-20 w-20 rounded-full bg-primary-fixed-dim/10 blur-xl" />

                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center">
                                        <img src="/images/assistantavatarinverted.png" alt={assistantName} className="h-12 w-12" />
                                    </div>
                                    <div>
                                        <h2 className="text-base font-bold leading-tight text-on-primary drop-shadow-sm">
                                            {assistantName}
                                        </h2>
                                        <p className="mt-0.5 text-[11px] font-medium leading-tight text-on-primary-container/90">
                                            Your Virtual Bayanihan Assistant
                                        </p>
                                    </div>
                                </div>
                                <div className="relative flex items-center gap-2">
                                    <button
                                        onClick={() => setShowClearConfirm((v) => !v)}
                                        className="text-[11px] font-medium text-on-primary/60 transition-all hover:text-on-primary"
                                    >
                                        Clear chat
                                    </button>
                                    {showClearConfirm && (
                                        <div className="absolute right-0 top-full mt-1 flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 shadow-lg animate-in fade-in slide-in-from-top-1 duration-200">
                                            <span className="text-[11px] font-medium text-on-surface whitespace-nowrap">Clear chat?</span>
                                            <button
                                                onClick={handleNewSession}
                                                className="rounded px-1.5 py-0.5 text-[11px] font-semibold text-red-600 transition-all hover:bg-red-50"
                                            >
                                                Yes
                                            </button>
                                            <button
                                                onClick={() => setShowClearConfirm(false)}
                                                className="rounded px-1.5 py-0.5 text-[11px] font-semibold text-on-surface-variant transition-all hover:bg-surface-container-low"
                                            >
                                                No
                                            </button>
                                        </div>
                                    )}
                                    <button
                                        onClick={() => setOpen(false)}
                                        className="flex h-8 w-8 items-center justify-center rounded-full text-on-primary/60 transition-all hover:bg-white/10 hover:text-on-primary"
                                        aria-label="Close chat"
                                    >
                                        <X className="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* ── Welcome card / quick help (stuck below header, not scrollable) ── */}
                        {!loading && quickHelpVisible && (
                            <div className="shrink-0">
                                <WelcomeCard onSuggestionClick={handleSuggestionClick} onClose={() => setQuickHelpVisible(false)} />
                            </div>
                        )}
                        {!loading && !quickHelpVisible && (
                            <div className="shrink-0 px-5 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setQuickHelpVisible(true)}
                                    className="rounded-full bg-primary/10 px-3 py-1 text-[11px] font-medium text-primary transition-all hover:bg-primary/20"
                                >
                                    + Show suggestions
                                </button>
                            </div>
                        )}

                        {/* ── Messages ── */}
                        <div
                            ref={listRef}
                            onScroll={handleScroll}
                            className="flex-1 overflow-y-auto px-5 py-4 scroll-smooth"
                        >
                            {messages.map(renderMessage)}
                            {loading && (
                                <div className="mt-4 flex gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                    <div className="mt-0.5 shrink-0">
                                        <img src="/images/assistantavatar.png" alt={assistantName} className="h-9 w-9 rounded-full" />
                                    </div>
                                    <LoadingMessages name={assistantName} />
                                </div>
                            )}

                            {showScrollBtn && (
                                <button
                                    onClick={() => scrollToBottom(true)}
                                    className="sticky bottom-2 left-1/2 z-10 mx-auto flex h-8 w-8 -translate-x-1/2 items-center justify-center rounded-full bg-white shadow-lg ring-1 ring-outline-variant transition-all hover:bg-surface-container-low hover:shadow-md active:scale-95"
                                    aria-label="Scroll to bottom"
                                >
                                    <svg
                                        className="h-4 w-4 text-on-surface-variant"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3"
                                        />
                                    </svg>
                                </button>
                            )}
                        </div>

                        {/* ── Input + Footer ── */}
                        <div className="shrink-0 border-t border-outline-variant/60 bg-white px-4 pb-3 pt-3 md:rounded-b-lg">
                            {showTurnstile && !turnstileVerified && (
                                <div className="mb-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-center">
                                    <p className="text-xs text-amber-800 mb-2 font-medium">Please verify you're human to continue chatting:</p>
                                    <TurnstileWidget onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} />
                                </div>
                            )}
                            <form onSubmit={handleSend} className="flex items-end gap-2">
                                <div className="relative flex-1">
                                    <textarea
                                        ref={inputRef}
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder="Ask about services, agencies, or case tracking..."
                                        rows={2}
                                        disabled={loading}
                                        className="block w-full resize-none rounded-xl border border-outline-variant/80 bg-surface-container-low px-4 py-3 pr-10 text-sm leading-relaxed placeholder:text-on-surface-variant/60 focus:border-primary/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:opacity-50"
                                        style={{ minHeight: '56px', maxHeight: '160px' }}
                                        onInput={(e) => {
                                            const el = e.currentTarget;
                                            el.style.height = 'auto';
                                            el.style.height = Math.min(el.scrollHeight, 160) + 'px';
                                        }}
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={loading || !input.trim()}
                                    className="flex h-[56px] w-[56px] shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/30 transition-all hover:brightness-110 hover:shadow-lg hover:shadow-primary/40 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none disabled:active:scale-100"
                                    aria-label="Send message"
                                >
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                            <div className="mt-2 text-center">
                                <p className="text-[10px] font-medium text-on-surface-variant/60">
                                    Powered by AI &middot; Responses may be inaccurate.
                                </p>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </>
    );
}
