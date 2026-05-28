import { usePage } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import axios from 'axios';
import { MessageCircle, X, Send } from 'lucide-react';

export default function ChatBot() {
    const { chatbot } = usePage().props;

    if (!chatbot?.enabled) return null;

    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([
        { role: 'bot', text: 'Hello! How can I help you today?' },
    ]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const listRef = useRef(null);

    useEffect(() => {
        if (listRef.current) {
            listRef.current.scrollTop = listRef.current.scrollHeight;
        }
    }, [messages]);

    async function handleSend(e) {
        e.preventDefault();
        if (!input.trim() || loading) return;

        const userMessage = input.trim();
        setInput('');
        setMessages((prev) => [...prev, { role: 'user', text: userMessage }]);
        setLoading(true);

        try {
            const { data } = await axios.post(route('chatbot.message'), {
                message: userMessage,
            });
            setMessages((prev) => [...prev, { role: 'bot', text: data.reply }]);
        } catch {
            setMessages((prev) => [
                ...prev,
                { role: 'bot', text: 'Sorry, I encountered an error. Please try again.' },
            ]);
        } finally {
            setLoading(false);
        }
    }

    return (
        <>
            {!open && (
                <button
                    onClick={() => setOpen(true)}
                    className="fixed bottom-4 right-4 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-500"
                >
                    <MessageCircle className="h-6 w-6" />
                </button>
            )}

            {open && (
                <div className="fixed bottom-4 right-4 z-50 flex w-80 flex-col rounded-lg bg-white shadow-xl">
                    <div className="flex items-center justify-between rounded-t-lg bg-indigo-600 px-4 py-3 text-white">
                        <span className="text-sm font-medium">Bayanihan Assistant</span>
                        <button onClick={() => setOpen(false)}>
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    <div ref={listRef} className="flex h-80 flex-col gap-3 overflow-y-auto p-4">
                        {messages.map((msg, i) => (
                            <div
                                key={i}
                                className={`max-w-[85%] rounded-lg px-3 py-2 text-sm ${
                                    msg.role === 'user'
                                        ? 'ml-auto bg-indigo-600 text-white'
                                        : 'bg-gray-100 text-gray-900'
                                }`}
                                dangerouslySetInnerHTML={{ __html: msg.text }}
                            />
                        ))}
                        {loading && (
                            <div className="max-w-[85%] rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-500">
                                Typing...
                            </div>
                        )}
                    </div>

                    <form onSubmit={handleSend} className="flex items-center gap-2 border-t p-3">
                        <input
                            type="text"
                            className="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                            placeholder="Type a message..."
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            disabled={loading}
                        />
                        <button
                            type="submit"
                            disabled={loading || !input.trim()}
                            className="flex h-9 w-9 items-center justify-center rounded-md bg-indigo-600 text-white disabled:opacity-50"
                        >
                            <Send className="h-4 w-4" />
                        </button>
                    </form>
                </div>
            )}
        </>
    );
}
