import { usePage, router } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import rehypeSanitize from 'rehype-sanitize';
import axios from 'axios';
import { MessageCircle, X, Send } from 'lucide-react';
import TurnstileWidget from '@/Components/TurnstileWidget';
import { getTurnstileError } from '@/lib/turnstile';
import ChatbotSources from '@/Components/ChatbotSources';

function createWelcomeMessage(name) {
    return {
        role: 'bot',
        text: `Hello! I'm **${name}**, your Virtual Bayanihan Assistant for the **One Window Bayanihan** system. How can I help you today? Feel free to ask about services, agencies, case tracking, or any OFW assistance you need.`,
        time: new Date(),
    };
}

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

function StreamingMarkdown({ text, stream, remarkPlugins, rehypePlugins, components }) {
    const [visible, setVisible] = useState(stream ? '' : text);

    useEffect(() => {
        if (!stream) {
            setVisible(text);
            return undefined;
        }

        let position = 0;
        const timer = setInterval(() => {
            position = Math.min(text.length, position + Math.max(1, Math.ceil((text.length - position) / 18)));
            setVisible(text.slice(0, position));
            if (position >= text.length) clearInterval(timer);
        }, 24);

        return () => clearInterval(timer);
    }, [text, stream]);

    return <ReactMarkdown remarkPlugins={remarkPlugins} rehypePlugins={rehypePlugins} components={components}>{visible}</ReactMarkdown>;
}

function FrequentlyAskedQuestions({ suggestions, onQuestionClick, onClose }) {
    const questions = suggestions ? Object.keys(suggestions) : [];
    return (
        <div className="relative animate-in fade-in slide-in-from-bottom-2 rounded-none bg-white px-5 py-3 shadow-sm ring-1 ring-outline-variant/60 duration-300">
            <div className="flex items-center justify-between mb-2">
                <p className="text-[11px] font-semibold text-on-surface-variant">Frequently Asked Questions</p>
                <button
                    type="button"
                    onClick={onClose}
                    className="flex h-5 w-5 items-center justify-center rounded text-on-surface-variant/50 transition-all hover:bg-surface-container-low hover:text-on-surface-variant"
                    aria-label="Hide frequently asked questions"
                >
                    <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {questions.map((question) => (
                    <button
                        key={question}
                        type="button"
                        onClick={() => onQuestionClick(question)}
                        className="rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-medium text-primary transition-all hover:bg-primary/20 hover:shadow-sm active:scale-95"
                    >
                        {question}
                    </button>
                ))}
            </div>
        </div>
    );
}

export default function ChatBot() {
    const { chatbot } = usePage().props;
    if (!chatbot?.enabled) return null;
    return <ChatBotSession />;
}

function ChatBotSession() {
    const { chatbot, turnstile } = usePage().props;
    const assistantName = chatbot?.assistant_name || 'Bayani';

    const [open, setOpen] = useState(false);
    // Conversation context is intentionally ephemeral: it is never persisted, so
    // every page load starts with a clean transcript. Follow-ups still resolve
    // within a visit because the send payload carries the in-memory history.
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef(null);
    const inputRef = useRef(null);
    const abortRef = useRef(null);
    const [showScrollBtn, setShowScrollBtn] = useState(false);
    const [showClearConfirm, setShowClearConfirm] = useState(false);
    const [quickHelpVisible, setQuickHelpVisible] = useState(true);
    const [turnstileToken, setTurnstileToken] = useState('');
    const [turnstileStatus, setTurnstileStatus] = useState('idle');
    const [verifiedUntil, setVerifiedUntil] = useState(turnstile?.verified_until || 0);
    const [widgetKey, setWidgetKey] = useState(0);
    const turnstileVerified = verifiedUntil * 1000 > Date.now();

    useEffect(() => {
        setVerifiedUntil(turnstile?.verified_until || 0);
    }, [turnstile?.verified_until]);

    useEffect(() => {
        if (!verifiedUntil) return undefined;
        const delay = verifiedUntil * 1000 - Date.now();
        if (delay <= 0) return undefined;
        const timer = setTimeout(() => {
            setVerifiedUntil(0);
            setShowTurnstile(true);
        }, Math.min(delay, 2147483647));
        return () => clearTimeout(timer);
    }, [verifiedUntil]);
    const [showTurnstile, setShowTurnstile] = useState(false);
    const [lastContext, setLastContext] = useState(null);
    const [quota, setQuota] = useState(null);
    const [quotaError, setQuotaError] = useState(false);
    const [quotaRefresh, setQuotaRefresh] = useState(0);
    const quotaVersion = useRef(0);

    useEffect(() => {
        if (!open) return undefined;
        let active = true;
        const version = ++quotaVersion.current;
        setQuota(null);
        setQuotaError(false);
        axios.get(route('chatbot.quota')).then(({ data }) => {
            if (active && quotaVersion.current === version) setQuota(data.quota);
        }).catch(() => {
            if (active && quotaVersion.current === version) setQuotaError(true);
        });
        return () => { active = false; };
    }, [open, quotaRefresh]);

    useEffect(() => {
        if (!open || !quota || quota.remaining > 0) return undefined;
        const delay = Math.max(0, new Date(quota.resets_at).getTime() - Date.now());
        if (!Number.isFinite(delay)) return undefined;
        const timer = setTimeout(() => setQuotaRefresh((value) => value + 1), Math.min(2147483647, Math.max(1000, delay)));
        return () => clearTimeout(timer);
    }, [open, quota]);

    useEffect(() => () => abortRef.current?.abort(), []);

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
        setLastContext(null);
        setShowClearConfirm(false);
        if (inputRef.current) inputRef.current.focus();
    }

    function handleSuggestionClick(label) {
        handleSend(null, label);
    }

    async function handleSend(e, overrideMessage) {
        e?.preventDefault();
        const userMessage = (overrideMessage || input).trim();
        if (!userMessage || loading || !quota || quota.remaining <= 0) return;

        // If turnstile is enabled and not verified, require the token
        const needsTurnstile = turnstile?.enabled && !turnstileVerified;
        if (needsTurnstile && !turnstileToken) {
            setShowTurnstile(true);
            const captchaMsg = getTurnstileError({ token: turnstileToken, status: turnstileStatus });
            setMessages((prev) => [
                ...prev,
                { role: 'user', text: userMessage, time: new Date() },
                { role: 'bot', text: captchaMsg || 'Please complete the verification challenge below before sending messages.', time: new Date() },
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
            // Send recent user queries so the backend can detect follow-ups.
            // Include both user and bot messages for full conversation context.
            // Only include paired exchanges (user followed by bot) to avoid
            // orphaned messages from page refreshes.
            const recentHistory = messages
                .filter((msg, i) => {
                    if (msg.role === 'bot') {
                        // Bot message is valid if the previous message is a user message
                        const prev = messages[i - 1];
                        return prev && prev.role === 'user';
                    }
                    // User message is valid if the next message is a bot reply
                    const next = messages[i + 1];
                    return next && next.role === 'bot';
                })
                .slice(-6)
                .map((msg) => ({
                    role: msg.role,
                    text: msg.text.slice(0, 1000),
                }));
            const payload = {
                message: userMessage,
                history: recentHistory,
            };
            if (lastContext) {
                payload.lastContext = lastContext;
            }
            // Include turnstile token on first (unverified) request
            if (needsTurnstile && turnstileToken) {
                payload.cf_turnstile_response = turnstileToken;
            }
            if (turnstileToken) {
                setTurnstileToken('');
                setWidgetKey((key) => key + 1);
            }
            const { data } = await axios.post(route('chatbot.message'), payload, { signal: controller.signal });
            if (controller.signal.aborted) return;
            quotaVersion.current++;
            setQuota(data.quota);
            if (turnstile?.enabled) {
                setVerifiedUntil(data.turnstile?.verified_until || 0);
                setShowTurnstile(!data.turnstile?.verified_until);
            }
            setMessages((prev) => [
                ...prev,
                { role: 'bot', text: data.reply, stream: true, time: new Date(), actions: data.actions || [], sources: data.sources || [] },
            ]);
            // Store context for follow-up augmentation on next turn
            setLastContext(data.lastContext || null);
        } catch (err) {
            if (axios.isCancel(err)) return;
            setLastContext(null);
            // Handle turnstile_required error from backend
            if (err.response?.status === 429 && err.response?.data?.status === 'quota_exceeded') {
                quotaVersion.current++;
                setQuota(err.response.data.quota);
                setMessages((prev) => [...prev, { role: 'bot', text: err.response.data.message, time: new Date() }]);
            } else if (err.response?.status === 422 && ['turnstile_required', 'turnstile_failed'].includes(err.response?.data?.error)) {
                setVerifiedUntil(0);
                setTurnstileToken('');
                setShowTurnstile(true);
                setMessages((prev) => [
                    ...prev,
                    { role: 'bot', text: 'Please complete the verification challenge below to continue.', time: new Date() },
                ]);
            } else {
                if (!err.response) setQuotaRefresh((value) => value + 1);
                setMessages((prev) => [
                    ...prev,
                    {
                        role: 'bot',
                        text: err.response?.status === 429 ? 'Too many requests. Please wait a moment and try again.' : 'Sorry, I encountered an error. Please try again.',
                        time: new Date(),
                    },
                ]);
            }
        } finally {
            if (abortRef.current === controller) {
                abortRef.current = null;
                setLoading(false);
            }
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
                                <img src="/images/assistantavatar.png?v=1" alt="" className="h-9 w-9 rounded-full object-contain" onError={(event) => { event.currentTarget.style.display = 'none'; }} />
                            </div>
                            <div className="overflow-hidden rounded-xl bg-white text-sm leading-relaxed text-on-surface shadow-sm ring-1 ring-outline-variant/60">
                                <div className="chatbot-markdown px-4 py-3">
                                    <StreamingMarkdown
                                         text={msg.text}
                                         stream={msg.stream}
                                        remarkPlugins={[remarkGfm]}
                                        rehypePlugins={[rehypeSanitize]}
                                        components={{
                                            a: ({ href, children }) => {
                                                let allowed = false;
                                                try {
                                                    const url = new URL(href, window.location.origin);
                                                    allowed = url.origin === window.location.origin && !url.search && !url.hash && ['/partners', '/contact', '/track'].includes(url.pathname);
                                                } catch { /* Invalid URL remains plain text. */ }
                                                return allowed ? <a href={href} className="text-primary underline underline-offset-2 focus-visible:outline focus-visible:outline-2">{children}</a> : <span>{children}</span>;
                                            },
                                            img: () => null,
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
                                        </StreamingMarkdown>
                                </div>
                                <ChatbotSources sources={msg.sources} />
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
                        <span className="ml-[48px] mt-1 flex items-center gap-1.5 px-1 text-[10px] text-on-surface-variant opacity-0 transition-opacity group-hover:opacity-100">
                            {msg.time ? formatTime(msg.time) : ''}
                        </span>
                    </div>
                )}
            </div>
        );
    }

    return (
        <>

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
                                <FrequentlyAskedQuestions suggestions={chatbot?.suggestions} onQuestionClick={handleSuggestionClick} onClose={() => setQuickHelpVisible(false)} />
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
                            className="flex-1 overflow-y-auto px-5 py-4 scroll-smooth owb-scroll-wide"
                        >
                            {messages.map(renderMessage)}
                            {loading && (
                                <div className="mt-4 flex gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                    <div className="mt-0.5 shrink-0">
                                        <img src="/images/assistantavatar.png?v=1" alt="" className="h-9 w-9 rounded-full object-contain" onError={(event) => { event.currentTarget.style.display = 'none'; }} />
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
                                    <TurnstileWidget key={widgetKey} onToken={setTurnstileToken} onExpire={() => setTurnstileToken('')} onStatusChange={setTurnstileStatus} />
                                </div>
                            )}
                            <div className="mb-2 text-xs text-on-surface-variant" role="status" aria-live="polite">
                                {quotaError ? <>Could not load your daily allowance. <button type="button" className="text-primary underline" onClick={() => setQuotaRefresh((value) => value + 1)}>Try again</button></> : !quota ? 'Loading daily allowance…' : <>
                                    {quota.remaining} of {quota.limit} messages remaining today.
                                    {quota.remaining === 0 && <> Resets {new Date(quota.resets_at).toLocaleString('en-US', { timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short' })} (Asia/Manila). Visit <a className="text-primary underline" href="/contact">Contact</a> or <a className="text-primary underline" href="/track">Track a case</a>.</>}
                                </>}
                            </div>
                            <form onSubmit={handleSend} className="flex items-end gap-2">
                                <div className="relative flex-1">
                                    <textarea
                                        ref={inputRef}
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder="Ask about services, agencies, or case tracking..."
                                        rows={2}
                                        disabled={loading || !quota || quota.remaining <= 0}
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
                                    disabled={loading || !quota || quota.remaining <= 0 || !input.trim()}
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
