import { usePage } from '@inertiajs/react';
import { useState, useRef, useEffect, useCallback } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import axios from 'axios';
import { MessageCircle, X, Send } from 'lucide-react';

const CHAT_HISTORY_KEY = 'owb_chat_history';

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
        /* localStorage unavailable — fall through to default */
    }
    return null;
}

function BayaniAvatar({ size = 32 }) {
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
            {/* Sun rays */}
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
            {/* Face circle */}
            <circle cx="16" cy="16" r="10" fill="#FDE68A" stroke="#F59E0B" strokeWidth="0.5" />
            {/* Hair */}
            <path d="M11 12Q16 8 21 12" stroke="#92400E" strokeWidth="1.5" fill="none" strokeLinecap="round" />
            {/* Eyes */}
            <circle cx="12.5" cy="15.5" r="1.2" fill="#92400E" />
            <circle cx="19.5" cy="15.5" r="1.2" fill="#92400E" />
            {/* Smile */}
            <path d="M11.5 18.5Q16 22 20.5 18.5" stroke="#92400E" strokeWidth="1.2" fill="none" strokeLinecap="round" />
            {/* Cheek blush */}
            <circle cx="10.5" cy="17.5" r="1.5" fill="#FCA5A5" opacity="0.4" />
            <circle cx="21.5" cy="17.5" r="1.5" fill="#FCA5A5" opacity="0.4" />
        </svg>
    );
}

function TypingDots() {
    return (
        <div className="flex items-center gap-1 px-1">
            <span className="h-2 w-2 animate-bounce rounded-full bg-blue-400 [animation-delay:0ms]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-blue-400 [animation-delay:150ms]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-blue-400 [animation-delay:300ms]" />
        </div>
    );
}

function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

export default function ChatBot() {
    const { chatbot } = usePage().props;
    if (!chatbot?.enabled) return null;

    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState(() => {
        const saved = loadChatHistory();
        if (saved) return saved;
        return [
            {
                role: 'bot',
                text: 'Hi there! I\'m **Bayani**, your assistant for the **Bayanihan One Window** system. How can I help you today?',
                time: new Date(),
            },
        ];
    });
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef(null);
    const inputRef = useRef(null);
    const [showScrollBtn, setShowScrollBtn] = useState(false);

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
        if (open && inputRef.current) {
            inputRef.current.focus();
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
            /* localStorage unavailable — silently fail */
        }
    }, [messages]);

    const handleScroll = useCallback(() => {
        if (!listRef.current) return;
        const { scrollTop, scrollHeight, clientHeight } = listRef.current;
        setShowScrollBtn(scrollHeight - scrollTop - clientHeight > 120);
    }, []);

    async function handleSend(e) {
        e?.preventDefault();
        if (!input.trim() || loading) return;

        const userMessage = input.trim();
        setInput('');
        setMessages((prev) => [
            ...prev,
            { role: 'user', text: userMessage, time: new Date() },
        ]);
        setLoading(true);

        try {
            const { data } = await axios.post(route('chatbot.message'), {
                message: userMessage,
            });
            setMessages((prev) => [
                ...prev,
                { role: 'bot', text: data.reply, time: new Date() },
            ]);
        } catch {
            setMessages((prev) => [
                ...prev,
                {
                    role: 'bot',
                    text: 'Sorry, I encountered an error. Please try again.',
                    time: new Date(),
                },
            ]);
        } finally {
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
                className={`flex gap-2.5 ${isUser ? 'flex-row-reverse' : ''} ${i === 0 && !isUser ? 'mt-0' : 'mt-3'}`}
            >
                {/* Avatar */}
                {!isUser && (
                    <div className="mt-0.5 shrink-0 drop-shadow-sm">
                        <BayaniAvatar size={32} />
                    </div>
                )}

                <div className={`group flex max-w-[80%] flex-col ${isUser ? 'items-end' : ''}`}>
                    {/* Bubble */}
                    <div
                        className={`prose prose-sm max-w-none rounded-lg px-4 py-2.5 text-sm leading-relaxed shadow-sm ${
                            isUser
                                ? 'bg-blue-800 text-white [&_p]:text-white [&_strong]:text-white [&_code]:bg-white/20 [&_code]:text-white'
                                : 'bg-white text-slate-800 ring-1 ring-inset ring-slate-200'
                        }`}
                    >
                        {isUser ? (
                            <p className="m-0 whitespace-pre-wrap break-words">{msg.text}</p>
                        ) : (
                            <div className="chatbot-markdown">
                                <ReactMarkdown
                                    remarkPlugins={[remarkGfm]}
                                    components={{
                                        a: ({ href, children }) => (
                                            <a
                                                href={href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-blue-800 underline underline-offset-2 hover:text-blue-700"
                                            >
                                                {children}
                                            </a>
                                        ),
                                        ul: ({ children }) => (
                                            <ul className="my-1 list-disc space-y-0.5 pl-5 marker:text-slate-400">
                                                {children}
                                            </ul>
                                        ),
                                        ol: ({ children }) => (
                                            <ol className="my-1 list-decimal space-y-0.5 pl-5 marker:text-slate-400">
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
                                                    className="rounded bg-slate-200/70 px-1.5 py-0.5 text-xs font-medium text-slate-800"
                                                    {...props}
                                                >
                                                    {children}
                                                </code>
                                            ) : (
                                                <code
                                                    className="block rounded-lg bg-slate-800 p-3 text-xs text-slate-100"
                                                    {...props}
                                                >
                                                    {children}
                                                </code>
                                            );
                                        },
                                        strong: ({ children }) => (
                                            <strong className="font-semibold text-slate-900">
                                                {children}
                                            </strong>
                                        ),
                                        h3: ({ children }) => (
                                            <h3 className="my-2 text-sm font-semibold text-slate-900">
                                                {children}
                                            </h3>
                                        ),
                                    }}
                                >
                                    {msg.text}
                                </ReactMarkdown>
                            </div>
                        )}
                    </div>

                    {/* Timestamp */}
                    <span className="mt-1 px-1 text-[10px] text-slate-400 opacity-0 transition-opacity group-hover:opacity-100">
                        {msg.time ? formatTime(msg.time) : ''}
                    </span>
                </div>
            </div>
        );
    }

    return (
        <>
            {/* Trigger button */}
            {!open && (
                <button
                    onClick={() => setOpen(true)}
                    className="group fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-blue-900 text-white shadow-lg shadow-blue-900/30 transition-all duration-200 hover:bg-blue-800 hover:shadow-xl hover:shadow-blue-900/40 active:scale-95"
                    aria-label="Open chat"
                >
                    <MessageCircle className="h-6 w-6 transition-transform duration-200 group-hover:scale-110" />
                </button>
            )}

            {/* Chat panel */}
            {open && (
                <>
                    {/* Backdrop for mobile */}
                    <div
                        className="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm md:hidden"
                        onClick={() => setOpen(false)}
                    />

                    <div className="fixed bottom-0 right-0 z-50 flex h-full w-full flex-col bg-white shadow-2xl md:bottom-5 md:right-5 md:h-[600px] md:w-[400px] md:rounded-xl md:border md:border-slate-200/80 md:shadow-2xl md:shadow-blue-900/10 animate-in slide-in-from-bottom-4 fade-in md:animate-in md:slide-in-from-bottom-2 md:fade-in duration-300">
                        {/* Header */}
                        <div className="flex shrink-0 items-center justify-between bg-gradient-to-r from-blue-900 to-blue-800 px-5 py-4 md:rounded-t-xl">
                            <div className="flex items-center gap-3">
                                <div className="drop-shadow-sm">
                                    <BayaniAvatar size={36} />
                                </div>
                                <div>
                                    <h2 className="text-sm font-semibold text-white">Bayani</h2>
                                    <div className="flex items-center gap-1.5">
                                        <span className="relative flex h-2 w-2">
                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
                                        </span>
                                        <span className="text-[11px] text-blue-200">
                                            {loading ? 'Typing...' : 'Online'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button
                                onClick={() => setOpen(false)}
                                className="flex h-8 w-8 items-center justify-center rounded-full text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                                aria-label="Close chat"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Messages */}
                        <div
                            ref={listRef}
                            onScroll={handleScroll}
                            className="flex-1 overflow-y-auto px-5 py-4 scroll-smooth"
                        >
                            {messages.length === 1 && !loading && (
                                <div className="mb-4 rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-900/80 ring-1 ring-blue-200/50">
                                    <span className="font-semibold">Tip:</span> Ask about agencies, services, case tracking, or OFW support.
                                </div>
                            )}
                            {messages.map(renderMessage)}
                            {loading && (
                                <div className="mt-3 flex gap-2.5">
                                    <div className="mt-0.5 shrink-0 drop-shadow-sm">
                                        <BayaniAvatar size={32} />
                                    </div>
                                    <div className="rounded-lg bg-white px-4 py-3 ring-1 ring-inset ring-slate-200">
                                        <TypingDots />
                                    </div>
                                </div>
                            )}

                            {/* Scroll to bottom button */}
                            {showScrollBtn && (
                                <button
                                    onClick={() => scrollToBottom(true)}
                                    className="sticky bottom-2 left-1/2 z-10 mx-auto flex h-8 w-8 -translate-x-1/2 items-center justify-center rounded-full bg-white shadow-lg ring-1 ring-slate-200 transition-colors hover:bg-slate-50"
                                    aria-label="Scroll to bottom"
                                >
                                    <svg
                                        className="h-4 w-4 text-slate-500"
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

                        {/* Input */}
                        <div className="shrink-0 border-t border-slate-100 bg-white px-4 py-3 md:rounded-b-xl">
                            <form onSubmit={handleSend} className="flex items-end gap-2">
                                <div className="relative flex-1">
                                    <textarea
                                        ref={inputRef}
                                        value={input}
                                        onChange={(e) => setInput(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        placeholder="Type a message..."
                                        rows={1}
                                        disabled={loading}
                                        className="block w-full resize-none rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 pr-10 text-sm placeholder:text-slate-400 focus:border-blue-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:opacity-50"
                                        style={{ minHeight: '42px', maxHeight: '120px' }}
                                        onInput={(e) => {
                                            const el = e.currentTarget;
                                            el.style.height = 'auto';
                                            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
                                        }}
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={loading || !input.trim()}
                                    className="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-blue-900 text-white shadow-sm transition-all hover:bg-blue-800 hover:shadow-md active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:active:scale-100"
                                    aria-label="Send message"
                                >
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                            <p className="mt-1.5 text-center text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                                {chatbot?.provider === 'gemini' ? 'Google Gemini' : chatbot?.provider === 'anthropic' ? 'Claude' : 'AI'}
                            </p>
                        </div>
                    </div>
                </>
            )}
        </>
    );
}
